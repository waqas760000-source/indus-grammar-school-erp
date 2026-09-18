<?php
/**
 * Indus Grammar School ERP - Premium Admin Dashboard
 * Version 7.0.0 — Commercial UI/UX Redesign with Interactive Chart.js Analytics
 */

$pageTitle = 'Admin Dashboard';
$breadcrumbActive = 'Admin Dashboard';
include_once __DIR__ . '/includes/header.php';

$currentUser = currentUser();
$userRole = $currentUser['role_code'] ?? ($currentUser['role_name'] ?? '');

// -------------------------------------------------------------
// Database Metrics Retrieval (Safe Server-side Aggregates)
// -------------------------------------------------------------
try {
    $db = Database::getConnection();

    // 1. Student Strength & Categorization
    $totalStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
    $totalBoys = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active' AND gender = 'Male'")->fetchColumn();
    $totalGirls = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active' AND gender = 'Female'")->fetchColumn();

    // School vs Academy counts
    $schoolStudents = 0;
    $academyStudents = 0;
    try {
        $schoolStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active' AND (academic_type = 'School' OR academic_type IS NULL OR academic_type = '')")->fetchColumn();
        $academyStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active' AND academic_type = 'Academy'")->fetchColumn();
    } catch (Exception $e) {
        $schoolStudents = $totalStudents;
    }

    // 2. Class-wise Student Distribution (for Bar Chart)
    $classLabels = [];
    $classData = [];
    try {
        $classRows = $db->query("SELECT c.class_name, COUNT(s.id) as student_count FROM classes c LEFT JOIN students s ON s.class_id = c.id AND s.status = 'Active' GROUP BY c.id, c.class_name ORDER BY c.id ASC LIMIT 10")->fetchAll();
        foreach ($classRows as $cr) {
            $classLabels[] = $cr['class_name'];
            $classData[] = (int)$cr['student_count'];
        }
    } catch (Exception $e) {
        $classLabels = ['Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5'];
        $classData = [0, 0, 0, 0, 0, 0];
    }

    // 3. Student Attendance Today
    $studentsPresent = (int)$db->query("SELECT COUNT(*) FROM attendance WHERE date = CURRENT_DATE AND status = 'Present'")->fetchColumn();
    $studentsAbsent = (int)$db->query("SELECT COUNT(*) FROM attendance WHERE date = CURRENT_DATE AND status = 'Absent'")->fetchColumn();
    $studentsLate = (int)$db->query("SELECT COUNT(*) FROM attendance WHERE date = CURRENT_DATE AND status = 'Late'")->fetchColumn();
    $studentsLeave = (int)$db->query("SELECT COUNT(*) FROM attendance WHERE date = CURRENT_DATE AND status = 'Leave'")->fetchColumn();

    $presentBoys = (int)$db->query("SELECT COUNT(a.id) FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.date = CURRENT_DATE AND a.status = 'Present' AND s.gender = 'Male'")->fetchColumn();
    $presentGirls = (int)$db->query("SELECT COUNT(a.id) FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.date = CURRENT_DATE AND a.status = 'Present' AND s.gender = 'Female'")->fetchColumn();

    $totalMarkedToday = $studentsPresent + $studentsAbsent + $studentsLate + $studentsLeave;
    $studentPresentPct = $totalMarkedToday > 0 ? round((($studentsPresent + $studentsLate) / $totalMarkedToday) * 100, 1) : 0;
    $studentAbsentPct = $totalMarkedToday > 0 ? round(($studentsAbsent / $totalMarkedToday) * 100, 1) : 0;

    // 4. Staff Summary & Attendance Today
    $totalStaff = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active'")->fetchColumn();
    $maleStaff = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active' AND gender = 'Male'")->fetchColumn();
    $femaleStaff = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active' AND gender = 'Female'")->fetchColumn();

    $staffPresent = (int)$db->query("SELECT COUNT(*) FROM staff_attendance WHERE date = CURRENT_DATE AND status = 'Present'")->fetchColumn();
    $staffAbsent = (int)$db->query("SELECT COUNT(*) FROM staff_attendance WHERE date = CURRENT_DATE AND status = 'Absent'")->fetchColumn();
    $totalStaffMarked = $staffPresent + $staffAbsent;
    $staffPresentPct = $totalStaffMarked > 0 ? round(($staffPresent / $totalStaffMarked) * 100, 1) : 0;

    // 5. Financial Statistics (Real Database Records Only)
    $todayCollection = (float)$db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE payment_date = CURRENT_DATE")->fetchColumn();
    $todayTransactionsCount = (int)$db->query("SELECT COUNT(*) FROM fee_payments WHERE payment_date = CURRENT_DATE")->fetchColumn();

    // Current Month Fee Summary
    $feeReceivable = (float)$db->query("SELECT COALESCE(SUM(total_payable), 0) FROM fee_ledger WHERE MONTH(due_date) = MONTH(CURRENT_DATE) AND YEAR(due_date) = YEAR(CURRENT_DATE)")->fetchColumn();
    $feeReceived = (float)$db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE) AND YEAR(payment_date) = YEAR(CURRENT_DATE)")->fetchColumn();
    $feeBalance = max(0, $feeReceivable - $feeReceived);
    $discountGiven = (float)$db->query("SELECT COALESCE(SUM(discount_amount), 0) FROM fee_ledger WHERE MONTH(due_date) = MONTH(CURRENT_DATE) AND YEAR(due_date) = YEAR(CURRENT_DATE)")->fetchColumn();
    $feeCollectionPct = $feeReceivable > 0 ? round(($feeReceived / $feeReceivable) * 100, 1) : 0;

    // Current Month Arrear Status
    $arrearsReceivable = (float)$db->query("SELECT COALESCE(SUM(total_payable - paid_amount), 0) FROM fee_ledger WHERE due_date < DATE_FORMAT(CURRENT_DATE ,'%Y-%m-01') AND status IN ('Pending', 'Partial')")->fetchColumn();
    $arrearsReceived = (float)$db->query("SELECT COALESCE(SUM(fp.amount_paid), 0) FROM fee_payments fp JOIN fee_ledger fl ON fp.ledger_id = fl.id WHERE fl.due_date < DATE_FORMAT(CURRENT_DATE ,'%Y-%m-01') AND MONTH(fp.payment_date) = MONTH(CURRENT_DATE) AND YEAR(fp.payment_date) = YEAR(CURRENT_DATE)")->fetchColumn();
    $arrearsBalance = max(0, $arrearsReceivable - $arrearsReceived);

    // Cash Summary
    $openingBalance = (float)$db->query("SELECT COALESCE(opening_balance, 0) FROM cash_register WHERE date = CURRENT_DATE LIMIT 1")->fetchColumn();
    $todayExpenses = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = CURRENT_DATE")->fetchColumn();
    $cashInHand = $openingBalance + $todayCollection - $todayExpenses;
    $closingBalance = (float)$db->query("SELECT COALESCE(closing_balance, 0) FROM cash_register WHERE date = CURRENT_DATE AND status = 'Closed' LIMIT 1")->fetchColumn();

    // 6. Announcements & Circulars
    require_once __DIR__ . '/models/Announcement.php';
    require_once __DIR__ . '/models/Circular.php';
    $activeAnnouncements = Announcement::activeForAudience('Everyone');
    $activeCirculars = Circular::activeForUser('School', 0);

    // 7. Recent Registrations & Recent Payment Transactions Tables
    $recentStudents = [];
    try {
        $recentStudents = $db->query("SELECT id, admission_no, first_name, last_name, gender, current_class, created_at FROM students ORDER BY id DESC LIMIT 5")->fetchAll();
    } catch (Exception $e) {}

    $recentPayments = [];
    try {
        $recentPayments = $db->query("SELECT fp.id, fp.receipt_no, fp.amount_paid, fp.payment_date, fp.payment_method, s.first_name, s.last_name, s.admission_no FROM fee_payments fp JOIN students s ON fp.student_id = s.id ORDER BY fp.id DESC LIMIT 5")->fetchAll();
    } catch (Exception $e) {}

    // 8. Audit Logs / Recent Activities
    $activities = [];
    try {
        $activities = $db->query("SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 5")->fetchAll();
    } catch (Exception $e) {}

} catch (Exception $e) {
    die("Database Error loading Dashboard: " . $e->getMessage());
}

$activeYear = defined('CURRENT_ACADEMIC_YEAR') ? CURRENT_ACADEMIC_YEAR : date('Y');
$adminDisplayName = sanitize($currentUser['username'] === 'waqas7600' ? 'Waqas Ali' : (ucfirst($currentUser['username'] ?? 'Administrator')));
?>

<!-- Custom Premium ERP Dashboard Styling -->
<style>
:root {
    --primary-navy: #0F172A;
    --primary-blue: #1D4ED8;
    --secondary-blue: #2563EB;
    --light-blue: #EFF6FF;
    --page-background: #F1F5F9;
    --card-white: #FFFFFF;
    --border-color: #E2E8F0;
    --main-text: #1E293B;
    --muted-text: #64748B;
    --success-color: #16A34A;
    --warning-color: #D97706;
    --danger-color: #DC2626;
    --purple-color: #7C3AED;
}

/* Dashboard Canvas Wrapper */
.dashboard-wrapper {
    background-color: var(--page-background);
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 100px);
}

/* Header Banner (Matches Student Registration Hero Style) */
.dashboard-hero-banner {
    background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 55%, var(--secondary-blue) 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.dashboard-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    pointer-events: none;
}

.hero-icon-wrapper {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

/* Welcome Card */
.welcome-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem 1.75rem;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.04);
}

/* Premium KPI Stat Cards */
.kpi-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.35rem 1.5rem;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.09);
    border-color: #cbd5e1;
}

.kpi-card-accent-primary { border-top: 4px solid var(--primary-blue); }
.kpi-card-accent-success { border-top: 4px solid var(--success-color); }
.kpi-card-accent-warning { border-top: 4px solid var(--warning-color); }
.kpi-card-accent-danger { border-top: 4px solid var(--danger-color); }
.kpi-card-accent-purple { border-top: 4px solid var(--purple-color); }
.kpi-card-accent-navy { border-top: 4px solid var(--primary-navy); }

.kpi-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}

.kpi-icon-blue { background-color: var(--light-blue); color: var(--primary-blue); }
.kpi-icon-green { background-color: #f0fdf4; color: var(--success-color); }
.kpi-icon-orange { background-color: #fffbeb; color: var(--warning-color); }
.kpi-icon-red { background-color: #fef2f2; color: var(--danger-color); }
.kpi-icon-purple { background-color: #f3e8ff; color: var(--purple-color); }
.kpi-icon-navy { background-color: #f8fafc; color: var(--primary-navy); }

.kpi-value {
    font-size: 1.95rem;
    font-weight: 800;
    color: var(--primary-navy);
    line-height: 1.2;
    margin-top: 0.4rem;
    margin-bottom: 0.2rem;
}

.kpi-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--muted-text);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Quick Access Module Cards */
.module-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 4px 10px -2px rgba(15, 23, 42, 0.03);
    display: flex;
    align-items: center;
    gap: 1rem;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
}

.module-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(29, 78, 216, 0.12);
    border-color: #bfdbfe;
    color: inherit;
}

.module-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--light-blue);
    color: var(--primary-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.module-card:hover .module-icon-box {
    background: var(--primary-blue);
    color: #ffffff;
}

.module-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--primary-navy);
    margin: 0;
}

.module-desc {
    font-size: 0.78rem;
    color: var(--muted-text);
    margin: 0;
}

/* Section Header Titles */
.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary-navy);
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.section-title i {
    color: var(--primary-blue);
}

/* Table Cards */
.table-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.04);
}

.table-card-header {
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--card-white);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.table-custom th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
}

.table-custom td {
    padding: 0.9rem 1.25rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: var(--main-text);
    border-bottom: 1px solid #f1f5f9;
}

.table-custom tr:last-child td {
    border-bottom: none;
}

/* Status Badges */
.badge-soft-success { background-color: #dcfce7; color: #15803d; font-weight: 600; padding: 0.35em 0.75em; border-radius: 8px; }
.badge-soft-warning { background-color: #fef3c7; color: #b45309; font-weight: 600; padding: 0.35em 0.75em; border-radius: 8px; }
.badge-soft-danger  { background-color: #fee2e2; color: #b91c1c; font-weight: 600; padding: 0.35em 0.75em; border-radius: 8px; }
.badge-soft-primary { background-color: #dbeafe; color: var(--primary-blue); font-weight: 600; padding: 0.35em 0.75em; border-radius: 8px; }
.badge-soft-purple  { background-color: #f3e8ff; color: #6b21a8; font-weight: 600; padding: 0.35em 0.75em; border-radius: 8px; }
</style>

<div class="dashboard-wrapper">

    <!-- 1. Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Admin Dashboard</li>
        </ol>
    </nav>

    <!-- 2. Main Dashboard Header Banner -->
    <div class="dashboard-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-wrapper">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="fw-bold mb-0 text-white fs-3">Admin Dashboard</h2>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">ERP Operational Desk</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Monitor student activities, attendance, fee collection, academic operations, and overall school performance from one central dashboard.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="text-end text-md-end bg-white bg-opacity-10 px-3 py-2 rounded-3 border border-white border-opacity-25">
                    <span class="d-block text-white-50 small" style="font-size: 0.75rem;"><i class="fa-regular fa-clock me-1"></i>Today's Date</span>
                    <span class="fw-bold text-white small"><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Welcome Section -->
    <div class="welcome-card mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="text-muted small text-uppercase fw-semibold tracking-wider d-block mb-1">Authenticated Session</span>
                <h4 class="fw-bold text-dark mb-1">Welcome to Indus Grammar School ERP, <?php echo $adminDisplayName; ?>!</h4>
                <p class="text-secondary small mb-0">Manage student registration, attendance, fee collection, accounts, payroll, examinations, and reports from one centralized system.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="px-3 py-2 bg-light rounded-3 border">
                    <span class="text-muted small d-block" style="font-size: 0.75rem;">Academic Session</span>
                    <span class="fw-bold text-dark small"><i class="fa-solid fa-graduation-cap text-primary me-1"></i><?php echo $activeYear; ?></span>
                </div>
                <div class="px-3 py-2 bg-light rounded-3 border">
                    <span class="text-muted small d-block" style="font-size: 0.75rem;">System Status</span>
                    <span class="badge bg-success-soft"><i class="fa-solid fa-circle-check me-1"></i>Active / Online</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Main Statistics KPI Cards -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Total Students -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card kpi-card-accent-primary">
                <div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="kpi-label">Total Students</span>
                        <div class="kpi-icon-box kpi-icon-blue"><i class="fa-solid fa-users"></i></div>
                    </div>
                    <div class="kpi-value"><?php echo number_format($totalStudents); ?></div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-3 small text-muted">
                    <span><i class="fa-solid fa-mars text-primary me-1"></i><?php echo $totalBoys; ?> Boys</span>
                    <span><i class="fa-solid fa-venus text-danger me-1"></i><?php echo $totalGirls; ?> Girls</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Today's Attendance -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card kpi-card-accent-success">
                <div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="kpi-label">Today's Attendance</span>
                        <div class="kpi-icon-box kpi-icon-green"><i class="fa-solid fa-user-check"></i></div>
                    </div>
                    <div class="kpi-value"><?php echo $studentPresentPct; ?>%</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-3 small text-muted">
                    <span class="text-success fw-semibold"><i class="fa-solid fa-check me-1"></i><?php echo $studentsPresent; ?> Present</span>
                    <span class="text-danger"><i class="fa-solid fa-xmark me-1"></i><?php echo $studentsAbsent; ?> Absent</span>
                </div>
            </div>
        </div>

        <!-- Card 3: Today's Fee Collection -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card kpi-card-accent-purple">
                <div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="kpi-label">Today's Collection</span>
                        <div class="kpi-icon-box kpi-icon-purple"><i class="fa-solid fa-money-bill-wave"></i></div>
                    </div>
                    <div class="kpi-value">Rs. <?php echo number_format($todayCollection); ?></div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-3 small text-muted">
                    <span><i class="fa-solid fa-receipt me-1"></i><?php echo $todayTransactionsCount; ?> Payment Receipts</span>
                    <a href="<?php echo APP_URL; ?>/modules/fees/collection.php" class="text-primary fw-semibold text-decoration-none">Collect <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
        </div>

        <!-- Card 4: Pending Fee Balance -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card kpi-card-accent-warning">
                <div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="kpi-label">Pending Fee Balance</span>
                        <div class="kpi-icon-box kpi-icon-orange"><i class="fa-solid fa-scale-unbalanced"></i></div>
                    </div>
                    <div class="kpi-value text-danger">Rs. <?php echo number_format($feeBalance); ?></div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-3 small text-muted">
                    <span>Collection Rate: <strong class="text-dark"><?php echo $feeCollectionPct; ?>%</strong></span>
                    <a href="<?php echo APP_URL; ?>/modules/fees/challan.php" class="text-primary fw-semibold text-decoration-none">Ledgers <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Quick Actions Section -->
    <div class="mb-4">
        <h5 class="section-title mb-3"><i class="fa-solid fa-cubes"></i>Quick Access ERP Modules</h5>
        <div class="row g-3">
            <!-- Module 1: Student Registration -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/students/registration.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-user-plus"></i></div>
                    <div>
                        <h6 class="module-title">Student Registration</h6>
                        <p class="module-desc">Register new student profiles</p>
                    </div>
                </a>
            </div>

            <!-- Module 2: Cashier Fee Collection -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/fees/collection.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-cash-register"></i></div>
                    <div>
                        <h6 class="module-title">Cashier Collection</h6>
                        <p class="module-desc">Collect fees & issue receipts</p>
                    </div>
                </a>
            </div>

            <!-- Module 3: Student Attendance Register -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/attendance/student.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-clipboard-user"></i></div>
                    <div>
                        <h6 class="module-title">Attendance Register</h6>
                        <p class="module-desc">Mark class daily attendance</p>
                    </div>
                </a>
            </div>

            <!-- Module 4: Fee Ledger Sheets -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/fees/challan.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <div>
                        <h6 class="module-title">Fee Ledger Sheets</h6>
                        <p class="module-desc">Manage student fee ledgers</p>
                    </div>
                </a>
            </div>

            <!-- Module 5: Fee Structure -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/fees/structure.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-tags"></i></div>
                    <div>
                        <h6 class="module-title">Fee Structure</h6>
                        <p class="module-desc">Configure class fee rules</p>
                    </div>
                </a>
            </div>

            <!-- Module 6: Accounts Ledger -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/accounts/ledger.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-building-columns"></i></div>
                    <div>
                        <h6 class="module-title">Accounts Ledger</h6>
                        <p class="module-desc">Financial cashbook & ledgers</p>
                    </div>
                </a>
            </div>

            <!-- Module 7: Daily Attendance Report -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/attendance/daily.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-calendar-day"></i></div>
                    <div>
                        <h6 class="module-title">Daily Attendance</h6>
                        <p class="module-desc">Daily class summary report</p>
                    </div>
                </a>
            </div>

            <!-- Module 8: Monthly Attendance Report -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/attendance/monthly.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-calendar-days"></i></div>
                    <div>
                        <h6 class="module-title">Monthly Attendance</h6>
                        <p class="module-desc">Monthly attendance register</p>
                    </div>
                </a>
            </div>

            <!-- Module 9: Attendance Settings -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/attendance/settings.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-sliders"></i></div>
                    <div>
                        <h6 class="module-title">Attendance Settings</h6>
                        <p class="module-desc">Configure rules & timings</p>
                    </div>
                </a>
            </div>

            <!-- Module 10: Student ID Card Generator -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/students/id_cards.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-id-card"></i></div>
                    <div>
                        <h6 class="module-title">Student ID Cards</h6>
                        <p class="module-desc">Generate student identity cards</p>
                    </div>
                </a>
            </div>

            <!-- Module 11: Family Phone List -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/students/family_phone_list.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-phone"></i></div>
                    <div>
                        <h6 class="module-title">Family Phone Directory</h6>
                        <p class="module-desc">Guardian contact directory</p>
                    </div>
                </a>
            </div>

            <!-- Module 12: Student Directory -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/students/list.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-address-book"></i></div>
                    <div>
                        <h6 class="module-title">Student Directory</h6>
                        <p class="module-desc">Browse & filter all students</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- 6. Interactive Visual Analytics (Chart.js Integration) -->
    <div class="row g-4 mb-4">
        <!-- Attendance Breakdown Chart -->
        <div class="col-12 col-lg-4">
            <div class="table-card p-4 h-100">
                <h5 class="section-title mb-3"><i class="fa-solid fa-chart-pie"></i>Today's Attendance Status</h5>
                <div style="height: 230px; position: relative;">
                    <canvas id="attendanceDoughnutChart"></canvas>
                </div>
                <div class="d-flex justify-content-around text-center mt-3 pt-2 border-top small">
                    <div><span class="d-block text-muted" style="font-size:0.75rem;">Present</span><strong class="text-success"><?php echo $studentsPresent; ?></strong></div>
                    <div><span class="d-block text-muted" style="font-size:0.75rem;">Absent</span><strong class="text-danger"><?php echo $studentsAbsent; ?></strong></div>
                    <div><span class="d-block text-muted" style="font-size:0.75rem;">Late</span><strong class="text-warning"><?php echo $studentsLate; ?></strong></div>
                    <div><span class="d-block text-muted" style="font-size:0.75rem;">Leave</span><strong class="text-info"><?php echo $studentsLeave; ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Monthly Financial Collections Chart -->
        <div class="col-12 col-lg-4">
            <div class="table-card p-4 h-100">
                <h5 class="section-title mb-3"><i class="fa-solid fa-chart-donut"></i>Monthly Fee Overview</h5>
                <div style="height: 230px; position: relative;">
                    <canvas id="financialDoughnutChart"></canvas>
                </div>
                <div class="d-flex justify-content-around text-center mt-3 pt-2 border-top small">
                    <div><span class="d-block text-muted" style="font-size:0.75rem;">Received</span><strong class="text-success">Rs. <?php echo number_format($feeReceived); ?></strong></div>
                    <div><span class="d-block text-muted" style="font-size:0.75rem;">Pending</span><strong class="text-danger">Rs. <?php echo number_format($feeBalance); ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Class Strength Distribution Bar Chart -->
        <div class="col-12 col-lg-4">
            <div class="table-card p-4 h-100">
                <h5 class="section-title mb-3"><i class="fa-solid fa-chart-bar"></i>Class Strength Distribution</h5>
                <div style="height: 230px; position: relative;">
                    <canvas id="classStrengthBarChart"></canvas>
                </div>
                <div class="text-center mt-3 pt-2 border-top small text-muted">
                    <span>Active Student Enrollment by Class</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Attendance & Financial Overview Panels -->
    <div class="row g-4 mb-4">
        <!-- Attendance Analytics Detail Panel -->
        <div class="col-12 col-lg-6">
            <div class="table-card h-100 p-4">
                <h5 class="section-title mb-3"><i class="fa-solid fa-clipboard-check"></i>Attendance Analytics Summary</h5>
                
                <?php if ($totalMarkedToday === 0): ?>
                    <div class="alert alert-light border text-center py-4 my-2 text-muted">
                        <i class="fa-solid fa-calendar-xmark fs-3 d-block mb-2 text-secondary"></i>
                        Attendance data is not available for today yet.
                    </div>
                <?php else: ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1 small fw-semibold">
                            <span class="text-dark">Student Present Rate</span>
                            <span class="text-success"><?php echo $studentPresentPct; ?>%</span>
                        </div>
                        <div class="progress" style="height: 10px; border-radius: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $studentPresentPct; ?>%"></div>
                        </div>
                    </div>

                    <div class="row g-2 text-center mb-3">
                        <div class="col-3">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-muted small" style="font-size: 0.75rem;">Present</span>
                                <span class="fw-bold text-success fs-6"><?php echo $studentsPresent; ?></span>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-muted small" style="font-size: 0.75rem;">Absent</span>
                                <span class="fw-bold text-danger fs-6"><?php echo $studentsAbsent; ?></span>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-muted small" style="font-size: 0.75rem;">Late</span>
                                <span class="fw-bold text-warning fs-6"><?php echo $studentsLate; ?></span>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-muted small" style="font-size: 0.75rem;">Leave</span>
                                <span class="fw-bold text-info fs-6"><?php echo $studentsLeave; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 border-top">
                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <span>Boys Present: <strong class="text-dark"><?php echo $presentBoys; ?></strong></span>
                            <span>Girls Present: <strong class="text-dark"><?php echo $presentGirls; ?></strong></span>
                            <span>Total Marked: <strong class="text-dark"><?php echo $totalMarkedToday; ?></strong></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Staff Attendance Bar -->
                <div class="mt-4 pt-3 border-top">
                    <h6 class="fw-bold text-dark mb-2 fs-6"><i class="fa-solid fa-users-gear me-2 text-primary"></i>Staff Attendance Today</h6>
                    <div class="d-flex justify-content-between align-items-center mb-1 small">
                        <span class="text-muted">Staff Present</span>
                        <span class="fw-bold text-dark"><?php echo $staffPresent; ?> / <?php echo $totalStaff; ?> (<?php echo $staffPresentPct; ?>%)</span>
                    </div>
                    <div class="progress" style="height: 8px; border-radius: 6px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $staffPresentPct; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Overview Panel -->
        <div class="col-12 col-lg-6">
            <div class="table-card h-100 p-4">
                <h5 class="section-title mb-3"><i class="fa-solid fa-vault"></i>Financial Overview (Current Month)</h5>
                
                <table class="table table-sm table-borderless small mb-3">
                    <tr class="border-bottom">
                        <td class="text-muted py-2">Total Monthly Fee Receivable:</td>
                        <td class="fw-bold text-dark py-2 text-end">Rs. <?php echo number_format($feeReceivable, 2); ?></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="text-muted py-2">Monthly Fee Received:</td>
                        <td class="fw-bold text-success py-2 text-end">Rs. <?php echo number_format($feeReceived, 2); ?></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="text-muted py-2">Pending Fee Balance:</td>
                        <td class="fw-bold text-danger py-2 text-end">Rs. <?php echo number_format($feeBalance, 2); ?></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="text-muted py-2">Discounts Given:</td>
                        <td class="fw-bold text-warning py-2 text-end">Rs. <?php echo number_format($discountGiven, 2); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-2">Collection Rate:</td>
                        <td class="fw-bold text-primary py-2 text-end"><?php echo $feeCollectionPct; ?>%</td>
                    </tr>
                </table>

                <!-- Cash in Hand & Cash Summary -->
                <div class="p-3 bg-light rounded-3 border mt-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark small"><i class="fa-solid fa-wallet text-success me-1"></i>Cash Register Today</span>
                        <span class="badge bg-primary-soft">Live Balance</span>
                    </div>
                    <div class="row g-2 text-center small">
                        <div class="col-4">
                            <span class="text-muted d-block" style="font-size:0.75rem;">Opening</span>
                            <strong class="text-dark">Rs. <?php echo number_format($openingBalance); ?></strong>
                        </div>
                        <div class="col-4">
                            <span class="text-muted d-block" style="font-size:0.75rem;">Collection</span>
                            <strong class="text-success">Rs. <?php echo number_format($todayCollection); ?></strong>
                        </div>
                        <div class="col-4">
                            <span class="text-muted d-block" style="font-size:0.75rem;">Expenses</span>
                            <strong class="text-danger">Rs. <?php echo number_format($todayExpenses); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 8. Dashboard Tables & System Activity -->
    <div class="row g-4 mb-4">
        <!-- Table 1: Recent Student Registrations -->
        <div class="col-12 col-lg-6">
            <div class="table-card h-100">
                <div class="table-card-header">
                    <h5 class="section-title mb-0"><i class="fa-solid fa-user-graduate"></i>Recent Student Registrations</h5>
                    <a href="<?php echo APP_URL; ?>/modules/students/list.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 small">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Admission No</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentStudents)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No recent student registrations found.</td>
                                </tr>
                            <?php else: foreach ($recentStudents as $st): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?php echo sanitize($st['admission_no'] ?: ('STD-' . $st['id'])); ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo sanitize($st['first_name'] . ' ' . $st['last_name']); ?></div>
                                        <span class="text-muted small" style="font-size:0.75rem;"><?php echo sanitize($st['gender']); ?></span>
                                    </td>
                                    <td><span class="badge badge-soft-primary"><?php echo sanitize($st['current_class'] ?: 'Unassigned'); ?></span></td>
                                    <td class="text-muted small"><?php echo date('d M Y', strtotime($st['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Table 2: Recent Fee Payment Transactions -->
        <div class="col-12 col-lg-6">
            <div class="table-card h-100">
                <div class="table-card-header">
                    <h5 class="section-title mb-0"><i class="fa-solid fa-receipt"></i>Recent Fee Transactions</h5>
                    <a href="<?php echo APP_URL; ?>/modules/fees/collection.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 small">New Payment</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentPayments)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No payment transactions recorded yet.</td>
                                </tr>
                            <?php else: foreach ($recentPayments as $pay): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo sanitize($pay['receipt_no']); ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo sanitize($pay['first_name'] . ' ' . $pay['last_name']); ?></div>
                                        <span class="text-muted small" style="font-size:0.75rem;"><?php echo sanitize($pay['admission_no']); ?></span>
                                    </td>
                                    <td class="fw-bold text-success">Rs. <?php echo number_format($pay['amount_paid'], 2); ?></td>
                                    <td class="text-muted small"><?php echo date('d M Y', strtotime($pay['payment_date'])); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 9. Notice Board & Recent System Activity Log -->
    <div class="row g-4">
        <!-- Notice Board -->
        <div class="col-12 col-lg-6">
            <div class="table-card p-4 h-100">
                <h5 class="section-title mb-3"><i class="fa-solid fa-bullhorn text-danger"></i>Notice Board & Circulars</h5>
                <?php if (empty($activeAnnouncements) && empty($activeCirculars)): ?>
                    <div class="alert alert-light border small text-muted mb-0">No active notices or circular announcements.</div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($activeAnnouncements as $ann): ?>
                            <div class="border-bottom pb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark small">
                                        <span class="badge bg-danger-soft text-xs me-1"><?php echo sanitize($ann['priority']); ?></span>
                                        <?php echo sanitize($ann['title']); ?>
                                    </span>
                                    <span class="text-muted small" style="font-size:0.75rem;"><?php echo date('d M Y', strtotime($ann['start_date'])); ?></span>
                                </div>
                                <p class="text-muted small mb-0"><?php echo sanitize($ann['content']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Audit Activity Log -->
        <div class="col-12 col-lg-6">
            <div class="table-card p-4 h-100">
                <h5 class="section-title mb-3"><i class="fa-solid fa-clock-rotate-left text-secondary"></i>Recent System Activity Log</h5>
                <?php if (empty($activities)): ?>
                    <div class="alert alert-light border small text-muted mb-0">No recent system activity recorded.</div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($activities as $log): ?>
                            <div class="d-flex gap-3 align-items-start border-bottom pb-2">
                                <div class="bg-light text-primary p-2 rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px; flex-shrink:0;">
                                    <i class="fa-solid fa-check text-primary small"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark small"><?php echo sanitize($log['action']); ?></div>
                                    <div class="text-muted small mt-1"><?php echo sanitize($log['description']); ?></div>
                                    <div class="text-muted small mt-1" style="font-size:0.7rem;">
                                        <i class="fa-regular fa-clock me-1"></i><?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?> by <strong><?php echo sanitize($log['username'] ?: 'System'); ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js Script Initialization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Attendance Doughnut Chart
    const ctxAtt = document.getElementById('attendanceDoughnutChart');
    if (ctxAtt) {
        new Chart(ctxAtt, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late', 'Leave'],
                datasets: [{
                    data: [<?php echo $studentsPresent; ?>, <?php echo $studentsAbsent; ?>, <?php echo $studentsLate; ?>, <?php echo $studentsLeave; ?>],
                    backgroundColor: ['#16a34a', '#dc2626', '#d97706', '#0ea5e9'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    }

    // 2. Financial Doughnut Chart
    const ctxFin = document.getElementById('financialDoughnutChart');
    if (ctxFin) {
        new Chart(ctxFin, {
            type: 'doughnut',
            data: {
                labels: ['Received (Rs.)', 'Pending (Rs.)', 'Discounts (Rs.)'],
                datasets: [{
                    data: [<?php echo $feeReceived; ?>, <?php echo $feeBalance; ?>, <?php echo $discountGiven; ?>],
                    backgroundColor: ['#16a34a', '#dc2626', '#d97706'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    }

    // 3. Class Strength Bar Chart
    const ctxClass = document.getElementById('classStrengthBarChart');
    if (ctxClass) {
        new Chart(ctxClass, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($classLabels); ?>,
                datasets: [{
                    label: 'Students',
                    data: <?php echo json_encode($classData); ?>,
                    backgroundColor: '#1d4ed8',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#f1f5f9" } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
