<!-- Sidebar Navigation Component -->
<?php
$userSession = currentUser();
$userFullName = trim(($userSession['first_name'] ?? '') . ' ' . ($userSession['last_name'] ?? ''));
if (empty($userFullName)) $userFullName = $_SESSION['username'] ?? 'System User';
$userRoleDisplay = $_SESSION['role_name'] ?? $_SESSION['role_code'] ?? 'Administrator';
$userInitials = strtoupper(substr($userFullName, 0, 1));
?>
<nav id="sidebar">
    <!-- Sidebar Header / Brand Logo -->
    <div class="sidebar-header">
        <div class="sidebar-brand-icon-wrapper" style="background: white; overflow: hidden; padding: 2px;">
            <?php $logoUrl = getSchoolLogoUrl(); if (!empty($logoUrl)): ?>
                <img src="<?php echo $logoUrl; ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
            <?php else: ?>
                <div style="background: linear-gradient(135deg, #1e3a8a, #2563eb); width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
            <?php endif; ?>
        </div>
        <div class="sidebar-brand-info">
            <div class="d-flex align-items-center gap-2">
                <span class="sidebar-brand-name">Indus Grammar</span>
                <span class="badge sidebar-pro-badge">PRO</span>
            </div>
            <span class="sidebar-brand-tag">School ERP v4.0</span>
        </div>
    </div>

    <!-- Scrollable container inside sidebar -->
    <div class="sidebar-scroll">
        <ul class="sidebar-menu" id="sidebarMenu">
            
            <!-- SECTION 1: MAIN MENU -->
            <div class="sidebar-category-header">
                <span>Main Overview</span>
            </div>

            <li class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" data-sidebar-title="Dashboard">
                <a href="<?php echo APP_URL; ?>/dashboard.php">
                    <span class="menu-label-wrap">
                        <span class="menu-icon-squircle icon-blue">
                            <i class="fa-solid fa-gauge-high"></i>
                        </span>
                        <span class="menu-text">Dashboard</span>
                    </span>
                </a>
            </li>

            <!-- SECTION 2: ACADEMICS & STUDENTS -->
            <div class="sidebar-category-header">
                <span>Academics & Students</span>
            </div>

            <?php 
            $diaryPages = ['daily_diary.php', 'edit_diary.php', 'diary_report.php', 'view_diary.php', 'print_diary.php'];
            $currentScript = basename($_SERVER['PHP_SELF']);
            $isStudentRegActive = str_contains($_SERVER['PHP_SELF'], '/modules/students/') && !in_array($currentScript, $diaryPages);
            $isStudentDiaryActive = str_contains($_SERVER['PHP_SELF'], '/modules/students/') && in_array($currentScript, $diaryPages);
            ?>
            <?php if (hasPermission('student_view')): ?>
            <li class="menu-item-has-children <?php echo $isStudentRegActive ? 'active' : ''; ?>" data-sidebar-title="Student Registration">
                <a href="#studentSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo $isStudentRegActive ? '' : 'collapsed'; ?>" aria-expanded="<?php echo $isStudentRegActive ? 'true' : 'false'; ?>">
                    <span class="menu-label-wrap">
                        <span class="menu-icon-squircle icon-sky">
                            <i class="fa-solid fa-user-graduate"></i>
                        </span>
                        <span class="menu-text">Student Registration</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo $isStudentRegActive ? 'show' : ''; ?>" id="studentSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo $currentScript === 'registration.php' ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/registration.php">Registration</a></li>
                    <li class="<?php echo $currentScript === 'card_report.php' ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/card_report.php">Student Card Report</a></li>
                    <li class="<?php echo $currentScript === 'family_phone_list.php' ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/family_phone_list.php">Family Phone Numbers List</a></li>
                    <li class="<?php echo $currentScript === 'profile_report.php' ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/profile_report.php">Student Profile Dossier</a></li>
                    <li class="<?php echo $currentScript === 'summary_report.php' ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/summary_report.php">Student Summary Report</a></li>
                    <li class="<?php echo $currentScript === 'student_details_report.php' ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/templates/student_details_report.php" target="_blank">Student Details Report</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('student_view')): ?>
            <li class="menu-item-has-children <?php echo $isStudentDiaryActive ? 'active' : ''; ?>" data-sidebar-title="Student Diary">
                <a href="#studentDiarySubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo $isStudentDiaryActive ? '' : 'collapsed'; ?>" aria-expanded="<?php echo $isStudentDiaryActive ? 'true' : 'false'; ?>">
                    <span class="menu-label-wrap">
                        <span class="menu-icon-squircle icon-amber">
                            <i class="fa-solid fa-book-bookmark"></i>
                        </span>
                        <span class="menu-text">Student Diary</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo $isStudentDiaryActive ? 'show' : ''; ?>" id="studentDiarySubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo ($currentScript === 'diary_report.php' && ($_GET['view'] ?? '') === 'dashboard') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/diary_report.php?view=dashboard">Diary Dashboard</a></li>
                    <li class="<?php echo $currentScript === 'daily_diary.php' ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/daily_diary.php">Daily Diary</a></li>
                    <li class="<?php echo $currentScript === 'edit_diary.php' ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/edit_diary.php">Edit Diary</a></li>
                    <li class="<?php echo ($currentScript === 'diary_report.php' && (empty($_GET['view']) || $_GET['view'] === 'report')) ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/diary_report.php">Diary Report</a></li>
                    <li class="<?php echo ($currentScript === 'diary_report.php' && ($_GET['view'] ?? '') === 'analysis') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/diary_report.php?view=analysis">Diary Analysis</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('attendance_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && !str_contains($_SERVER['PHP_SELF'], '/staff') ? 'active' : ''; ?>" data-sidebar-title="Student Attendance">
                <a href="#attendanceSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && !str_contains($_SERVER['PHP_SELF'], '/staff')) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && !str_contains($_SERVER['PHP_SELF'], '/staff')) ? 'true' : 'false'; ?>">
                    <span class="menu-label-wrap">
                        <span class="menu-icon-squircle icon-emerald">
                            <i class="fa-solid fa-calendar-days"></i>
                        </span>
                        <span class="menu-text">Student Attendance</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && !str_contains($_SERVER['PHP_SELF'], '/staff')) ? 'show' : ''; ?>" id="attendanceSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/student.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/student.php">Mark Attendance</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/daily.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/daily.php">Daily Report</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/monthly.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/monthly.php">Monthly Report</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/reports.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/reports.php">Attendance Register</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/settings.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/settings.php">Attendance Settings</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- SECTION 3: FINANCE & ACCOUNTS -->
            <div class="sidebar-category-header">
                <span>Finance & Accounts</span>
            </div>

            <?php if (hasPermission('fee_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/fees/') && !str_contains($_SERVER['PHP_SELF'], '/expenses') ? 'active' : ''; ?>" data-sidebar-title="Fee Collection">
                <a href="#feeSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/fees/') && !str_contains($_SERVER['PHP_SELF'], '/expenses')) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/fees/') && !str_contains($_SERVER['PHP_SELF'], '/expenses')) ? 'true' : 'false'; ?>">
                    <span class="menu-label-wrap">
                        <span class="menu-icon-squircle icon-green">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </span>
                        <span class="menu-text">Fee Collection</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/fees/') && !str_contains($_SERVER['PHP_SELF'], '/expenses')) ? 'show' : ''; ?>" id="feeSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/structure.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/structure.php">Fee Structure</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/collection.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/collection.php">Collect Fee</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/discounts.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/discounts.php">Discount Fee</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/challan.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/challan.php">Fee Challan</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/dues.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/dues.php">Pending Dues</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/receipts.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/receipts.php">Fee Receipts</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/reports.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/reports.php">Fee Reports</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('cash_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/cash/') || str_contains($_SERVER['PHP_SELF'], '/expenses') ? 'active' : ''; ?>" data-sidebar-title="Accounts & Cash">
                <a href="#accountsSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/cash/') || str_contains($_SERVER['PHP_SELF'], '/expenses')) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/cash/') || str_contains($_SERVER['PHP_SELF'], '/expenses')) ? 'true' : 'false'; ?>">
                    <span class="menu-label-wrap">
                        <span class="menu-icon-squircle icon-cyan">
                            <i class="fa-solid fa-wallet"></i>
                        </span>
                        <span class="menu-text">Accounts & Cash</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/cash/') || str_contains($_SERVER['PHP_SELF'], '/expenses')) ? 'show' : ''; ?>" id="accountsSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/dashboard.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/dashboard.php">Accounts Dashboard</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/income.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/income.php">Income Ledger</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/expenses.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/expenses.php">Home Expenses</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/cashbook.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/cashbook.php">Cash Book Ledger</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/bank.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/bank.php">Bank Transactions</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/financial_reports.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/financial_reports.php">Financial Reports</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/categories.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/categories.php">Expense Categories</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- SECTION 4: ADMINISTRATION & HR -->
            <div class="sidebar-category-header">
                <span>Administration & HR</span>
            </div>

            <?php if (hasPermission('system_settings')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/administration/') ? 'active' : ''; ?>" data-sidebar-title="Admin Panel">
                <a href="#adminSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/administration/') ? '' : 'collapsed'; ?>" aria-expanded="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/administration/') ? 'true' : 'false'; ?>">
                    <span class="menu-label-wrap">
                        <span class="menu-icon-squircle icon-purple">
                            <i class="fa-solid fa-sliders"></i>
                        </span>
                        <span class="menu-text">Admin Panel</span>
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

            <?php if (hasPermission('attendance_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && str_contains($_SERVER['PHP_SELF'], '/staff') ? 'active' : ''; ?>" data-sidebar-title="Staff Attendance">
                <a href="#staffAttendanceSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && str_contains($_SERVER['PHP_SELF'], '/staff')) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && str_contains($_SERVER['PHP_SELF'], '/staff')) ? 'true' : 'false'; ?>">
                    <span class="menu-label-wrap">
                        <span class="menu-icon-squircle icon-indigo">
                            <i class="fa-solid fa-user-clock"></i>
                        </span>
                        <span class="menu-text">Staff Attendance</span>
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

            <?php if (hasPermission('hr_view')): ?>
            <li class="menu-item-has-children <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/staff/') && !str_contains($_SERVER['PHP_SELF'], '/payroll.php') && !str_contains($_SERVER['PHP_SELF'], '/salary_slip.php')) || str_contains($_SERVER['PHP_SELF'], '/payroll.php') ? 'active' : ''; ?>" data-sidebar-title="HR & Staff">
                <a href="#hrStaffSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/staff/') || str_contains($_SERVER['PHP_SELF'], '/payroll.php')) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/staff/') || str_contains($_SERVER['PHP_SELF'], '/payroll.php')) ? 'true' : 'false'; ?>">
                    <span class="menu-label-wrap">
                        <span class="menu-icon-squircle icon-teal">
                            <i class="fa-solid fa-users-gear"></i>
                        </span>
                        <span class="menu-text">HR & Staff</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/staff/') || str_contains($_SERVER['PHP_SELF'], '/payroll.php')) ? 'show' : ''; ?>" id="hrStaffSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/list.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/staff/list.php">Staff Directory</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/payroll.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/staff/payroll.php">Staff Payrolls</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- SECTION 5: EXAMINATIONS & REPORTS -->
            <div class="sidebar-category-header">
                <span>Examinations & Reports</span>
            </div>

            <?php if (hasPermission('exam_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/examination/') ? 'active' : ''; ?>" data-sidebar-title="Examination">
                <a href="#examinationSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/examination/') ? '' : 'collapsed'; ?>" aria-expanded="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/examination/') ? 'true' : 'false'; ?>">
                    <span class="menu-label-wrap">
                        <span class="menu-icon-squircle icon-orange">
                            <i class="fa-solid fa-file-signature"></i>
                        </span>
                        <span class="menu-text">Examination</span>
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

            <?php if (hasPermission('report_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/reports/') ? 'active' : ''; ?>" data-sidebar-title="Reports Hub">
                <a href="#reportsSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/reports/') ? '' : 'collapsed'; ?>" aria-expanded="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/reports/') ? 'true' : 'false'; ?>">
                    <span class="menu-label-wrap">
                        <span class="menu-icon-squircle icon-rose">
                            <i class="fa-solid fa-chart-pie"></i>
                        </span>
                        <span class="menu-text">Reports Hub</span>
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
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/custom.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/custom.php">Custom Reports</a></li>
                </ul>
            </li>
            <?php endif; ?>

        </ul>
    </div>

    <!-- Floating Executive User Profile Card at Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user-card">
            <div class="sidebar-user-avatar-wrapper">
                <div class="sidebar-user-avatar">
                    <?php echo htmlspecialchars($userInitials); ?>
                </div>
                <span class="user-status-dot" title="System Active"></span>
            </div>
            <div class="sidebar-user-info">
                <span class="sidebar-user-name" title="<?php echo htmlspecialchars($userFullName); ?>"><?php echo htmlspecialchars($userFullName); ?></span>
                <span class="sidebar-user-role"><?php echo htmlspecialchars($userRoleDisplay); ?></span>
            </div>
            <a href="<?php echo APP_URL; ?>/logout.php" class="sidebar-logout-btn" title="Logout of System">
                <i class="fa-solid fa-power-off"></i>
            </a>
        </div>
    </div>
</nav>

<!-- Mobile Overlay Layer -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>