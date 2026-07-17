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
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/dashboard.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/administration/dashboard.php">Admin Dashboard</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/users.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/administration/users.php">User Management</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/roles.php') || str_contains($_SERVER['PHP_SELF'], '/permissions.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/administration/roles.php">Roles & Permissions</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/settings.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/administration/settings.php">School Settings</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/academic.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/administration/academic.php">Academic Settings</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/backup.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/administration/backup.php">Backup & Restore</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/logs.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/administration/logs.php">Audit Logs</a></li>
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
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/dashboard.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/dashboard.php">Accounts Dashboard</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/income.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/income.php">Income Ledger</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/expenses.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/expenses.php">Home Expenses</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/opening_balance.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/opening_balance.php">Cash Opening</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/closing.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/closing.php">Cash Closing</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/cashbook.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/cashbook.php">Cash Book Ledger</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/bank.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/bank.php">Bank Transactions</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/financial_reports.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/financial_reports.php">Financial Reports</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/categories.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/categories.php">Expense Categories</a></li>
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
                <ul class="collapse list-unstyled submenu <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && str_contains($_SERVER['PHP_SELF'], '/staff_')) ? 'show' : ''; ?>" id="staffAttendanceSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/staff_mark.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/staff_mark.php">Mark Attendance</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/staff_daily.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/staff_daily.php">Daily Report</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/staff_monthly.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/staff_monthly.php">Monthly Report</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/staff_register.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/staff_register.php">Attendance Register</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/staff_leave.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/staff_leave.php">Leave Management</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/staff_settings.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/staff_settings.php">Attendance Settings</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- 8. HR & Staff -->
            <?php if (hasPermission('hr_view')): ?>
            <li class="menu-item-has-children <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/staff/') && !str_contains($_SERVER['PHP_SELF'], '/payroll.php') && !str_contains($_SERVER['PHP_SELF'], '/salary_slip.php')) || str_contains($_SERVER['PHP_SELF'], '/payroll.php') ? 'active' : ''; ?>">
                <a href="#hrStaffSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/staff/') || str_contains($_SERVER['PHP_SELF'], '/payroll.php')) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/staff/') || str_contains($_SERVER['PHP_SELF'], '/payroll.php')) ? 'true' : 'false'; ?>">
                    <span>
                        <i class="fa-solid fa-users-gear"></i>
                        <span>HR & Staff</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/staff/') || str_contains($_SERVER['PHP_SELF'], '/payroll.php')) ? 'show' : ''; ?>" id="hrStaffSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/list.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/staff/list.php">Staff Directory</a></li>
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
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/dashboard.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/dashboard.php">Exam Dashboard</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/exams.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/exams.php">Exam Types</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/schedule.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/schedule.php">Exam Schedule</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/subjects.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/subjects.php">Subject Setup</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/marks.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/marks.php">Marks Entry</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/grades.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/grades.php">Grade Setup</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/results.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/results.php">Class Results</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/report_cards.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/report_cards.php">Report Cards</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/positions.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/positions.php">Class Positions</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/promotions.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/promotions.php">Promotions</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/reports.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/reports.php">Exam Reports</a></li>
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
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/dashboard.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/dashboard.php">Report Dashboard</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/students.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/students.php">Student Reports</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/attendance.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/attendance.php">Attendance Reports</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/fees.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/fees.php">Fee Reports</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/accounts.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/accounts.php">Accounts Reports</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/examination.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/examination.php">Examination Reports</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/staff.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/staff.php">Staff Reports</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/payroll.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/payroll.php">Payroll Reports</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/communication.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/communication.php">Communication Reports</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/custom.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/custom.php">Custom Reports</a></li>
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
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/dashboard.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/communication/dashboard.php">Comm Dashboard</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/sms.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/communication/sms.php">SMS Dispatcher</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/email.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/communication/email.php">Email Broadcast</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/announcements.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/communication/announcements.php">Announcements Log</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/circulars.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/communication/circulars.php">Printable Circulars</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/history.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/communication/history.php">Comm History</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/templates.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/communication/templates.php">Message Templates</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/settings.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/communication/settings.php">Gateway Settings</a></li>
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
