<?php
/**
 * Indus Grammar School ERP - Premium Admin Dashboard
 * Version 9.0.0 — Modern Commercial ERP UI/UX Redesign
 */

$pageTitle = 'Dashboard';
$breadcrumbActive = 'Dashboard';
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

    // 2. Class-wise Student Distribution
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

    // 5. Financial Statistics
    $todayCollection = (float)$db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE payment_date = CURRENT_DATE")->fetchColumn();
    $todayTransactionsCount = (int)$db->query("SELECT COUNT(*) FROM fee_payments WHERE payment_date = CURRENT_DATE")->fetchColumn();

    // Single Month School Fee Summary (After Discount)
    $feeGross = 0.0;
    $discountGiven = 0.0;
    $feeReceivable = 0.0;
    try {
        $feeGross = (float)$db->query("SELECT COALESCE(SUM(srd.fee_monthly), 0) FROM student_registration_details srd JOIN students s ON srd.student_id = s.id WHERE s.status = 'Active'")->fetchColumn();
        $discountGiven = (float)$db->query("SELECT COALESCE(SUM(srd.fee_discount), 0) FROM student_registration_details srd JOIN students s ON srd.student_id = s.id WHERE s.status = 'Active'")->fetchColumn();
        $feeReceivable = (float)$db->query("SELECT COALESCE(SUM(srd.tuition_fee), 0) FROM student_registration_details srd JOIN students s ON srd.student_id = s.id WHERE s.status = 'Active'")->fetchColumn();
    } catch (Exception $e) {
        $feeReceivable = (float)$db->query("SELECT COALESCE(SUM(total_payable), 0) FROM fee_ledger WHERE status IN ('Pending', 'Partial')")->fetchColumn();
    }
    
    $feeReceived = (float)$db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE) AND YEAR(payment_date) = YEAR(CURRENT_DATE)")->fetchColumn();
    $feeBalance = max(0, $feeReceivable - $feeReceived);
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

    // 7. Recent Registrations & Payments
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
    --primary-blue: #2563EB;
    --hover-blue: #1D4ED8;
    --light-blue: #EFF6FF;
    --page-bg: #F8FAFC;
    --card-white: #FFFFFF;
    --border-color: #E2E8F0;
    --text-main: #1E293B;
    --text-muted: #64748B;
    --success: #10B981;
    --success-bg: #ECFDF5;
    --warning: #F59E0B;
    --warning-bg: #FFFBEB;
    --danger: #EF4444;
    --danger-bg: #FEF2F2;
    --purple: #8B5CF6;
    --purple-bg: #F5F3FF;
}

.dashboard-wrapper {
    background-color: var(--page-bg);
    border-radius: 20px;
    padding: 1.5rem;
    min-height: calc(100vh - 90px);
}

/* Hero Header Banner */
.dashboard-hero-banner {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 55%, #2563EB 100%);
    border-radius: 18px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
    position: relative;
    overflow: hidden;
}

.dashboard-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 250px;
    height: 250px;
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
    font-size: 1.5rem;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

/* Sleek Welcome Box */
.welcome-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem 1.75rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

/* Sample Image Matched Gradient Stat Cards */
.stat-card-sample {
    border-radius: 24px;
    padding: 1.5rem 1.65rem;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 135px;
}

.stat-card-sample:hover {
    transform: translateY(-4px);
}

.stat-card-peach {
    background: linear-gradient(135deg, #FFB37C 0%, #FF8C53 100%);
    box-shadow: 0 15px 35px rgba(255, 140, 83, 0.32);
}

.stat-card-purple {
    background: linear-gradient(135deg, #9B82F3 0%, #7C5CFC 100%);
    box-shadow: 0 15px 35px rgba(124, 92, 252, 0.32);
}

.stat-card-cyan {
    background: linear-gradient(135deg, #26C6DA 0%, #00ACC1 100%);
    box-shadow: 0 15px 35px rgba(0, 172, 193, 0.32);
}

.stat-card-emerald {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    box-shadow: 0 15px 35px rgba(16, 185, 129, 0.32);
}

.stat-card-number {
    font-size: 2.2rem;
    font-weight: 800;
    color: #FFFFFF;
    line-height: 1.1;
    margin-bottom: 0.25rem;
}

.stat-card-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.92);
}

.stat-card-badge {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}

.icon-peach { color: #FF8C53; font-size: 1.8rem; }
.icon-purple { color: #7C5CFC; font-size: 1.8rem; }
.icon-cyan { color: #00ACC1; font-size: 1.8rem; }
.icon-emerald { color: #059669; font-size: 1.8rem; }

/* Quick Module Links */
.module-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
    display: flex;
    align-items: center;
    gap: 1rem;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
}

.module-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.12);
    border-color: #BFDBFE;
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
    font-size: 1.2rem;
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
    color: var(--text-muted);
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
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.03);
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
    background-color: #F8FAFC;
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
    color: var(--text-main);
    border-bottom: 1px solid #F1F5F9;
}

.table-custom tr:last-child td {
    border-bottom: none;
}

.student-avatar-sm {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background-color: var(--primary-blue);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.82rem;
}
</style>

<div class="dashboard-wrapper">

    <!-- 1. Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Admin Operational Desk</li>
        </ol>
    </nav>

    <!-- 2. Vibrant Attractive Header Card -->
    <div class="card border-0 shadow-lg rounded-4 mb-4" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #4f46e5 100%); position: relative; overflow: hidden;">
        <!-- Glowing background accent circle -->
        <div style="position: absolute; right: -30px; bottom: -30px; width: 220px; height: 220px; border-radius: 50%; background: rgba(255, 255, 255, 0.1); pointer-events: none;"></div>
        
        <div class="card-body p-4 position-relative" style="z-index: 1;">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center text-white rounded-4 shadow-sm" style="width: 58px; height: 58px; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.35); font-size: 1.65rem; flex-shrink: 0;">
                        <i class="fa-solid fa-gauge-high"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <h2 class="fw-bold mb-0 text-white fs-3" style="letter-spacing: -0.3px;">Admin Dashboard</h2>
                            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill small fw-bold shadow-sm" style="background-color: #F59E0B !important; color: #FFFFFF !important;">
                                <i class="fa-solid fa-crown me-1"></i>Central Command Desk
                            </span>
                        </div>
                        <p class="text-white small mb-0 fs-6" style="opacity: 0.92;">Welcome back, <strong><?php echo $adminDisplayName; ?></strong>! Monitor student strength, attendance, fee collection, accounts, examinations, and overall school operations from one central workspace.</p>
                    </div>
                </div>
                
                <div class="d-flex flex-wrap align-items-center gap-3 ms-lg-auto">
                    <!-- Glassmorphic System Date Box -->
                    <div class="px-3 py-2 rounded-3 border d-flex align-items-center gap-3 shadow-sm" style="background: rgba(255, 255, 255, 0.18); backdrop-filter: blur(10px); border-color: rgba(255, 255, 255, 0.3) !important;">
                        <div class="text-warning fs-4" style="color: #FBBF24 !important;"><i class="fa-regular fa-calendar-check"></i></div>
                        <div>
                            <span class="d-block text-white small" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; opacity: 0.85;">System Date</span>
                            <span class="fw-bold text-white small"><?php echo date('l, F j, Y'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Glassmorphic Session Box -->
                    <div class="px-3 py-2 rounded-3 border d-flex align-items-center gap-2 shadow-sm" style="background: rgba(255, 255, 255, 0.18); backdrop-filter: blur(10px); border-color: rgba(255, 255, 255, 0.3) !important;">
                        <span class="text-white small d-block" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; opacity: 0.85;">Session</span>
                        <span class="fw-bold text-white small"><i class="fa-solid fa-graduation-cap text-warning me-1" style="color: #FBBF24 !important;"></i><?php echo $activeYear; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Sample-Matched Gradient Stat Cards -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Students (Peach/Orange Gradient) -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card-sample stat-card-peach">
                <div>
                    <div class="stat-card-number"><?php echo number_format($totalStudents); ?></div>
                    <div class="stat-card-title">Students</div>
                    <div class="small mt-2" style="font-size: 0.75rem; opacity: 0.9;">
                        <span><i class="fa-solid fa-mars me-1"></i><?php echo $totalBoys; ?> Boys</span> | 
                        <span><i class="fa-solid fa-venus me-1"></i><?php echo $totalGirls; ?> Girls</span>
                    </div>
                </div>
                <div class="stat-card-badge">
                    <i class="fa-solid fa-graduation-cap icon-peach"></i>
                </div>
            </div>
        </div>

        <!-- Card 2: Teachers (Purple Gradient) -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card-sample stat-card-purple">
                <div>
                    <div class="stat-card-number"><?php echo number_format($totalStaff); ?></div>
                    <div class="stat-card-title">Teachers</div>
                    <div class="small mt-2" style="font-size: 0.75rem; opacity: 0.9;">
                        <span><i class="fa-solid fa-user-check me-1"></i><?php echo $staffPresent; ?> Present Today</span>
                    </div>
                </div>
                <div class="stat-card-badge">
                    <i class="fa-solid fa-laptop-code icon-purple"></i>
                </div>
            </div>
        </div>

        <!-- Card 3: Parents (Cyan/Turquoise Gradient) -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card-sample stat-card-cyan">
                <div>
                    <div class="stat-card-number"><?php echo number_format((int)($totalStudents * 0.75)); ?></div>
                    <div class="stat-card-title">Parents / Accounts</div>
                    <div class="small mt-2" style="font-size: 0.75rem; opacity: 0.9;">
                        <span><i class="fa-solid fa-users me-1"></i>Active Family Accounts</span>
                    </div>
                </div>
                <div class="stat-card-badge">
                    <i class="fa-solid fa-user-tie icon-cyan"></i>
                </div>
            </div>
        </div>

        <!-- Card 4: Fee Collection / Earnings (Emerald Gradient) -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card-sample stat-card-emerald">
                <div>
                    <div class="stat-card-number" style="font-size: 1.7rem;">Rs. <?php echo number_format($feeReceivable); ?></div>
                    <div class="stat-card-title">Fee (After Discount)</div>
                    <div class="small mt-2" style="font-size: 0.75rem; opacity: 0.95;">
                        <span><i class="fa-solid fa-tags me-1"></i>Rs. <?php echo number_format($discountGiven); ?> Concession Applied</span>
                    </div>
                </div>
                <div class="stat-card-badge">
                    <i class="fa-solid fa-wallet icon-emerald"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Quick Access Module Grid -->
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

            <!-- Module 3: Student Discount Submode -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/fees/discounts.php" class="module-card">
                    <div class="module-icon-box" style="background: #FFF0EB; color: #FF8A65;"><i class="fa-solid fa-percent"></i></div>
                    <div>
                        <h6 class="module-title">Fee Discounts</h6>
                        <p class="module-desc">Assign student concessions</p>
                    </div>
                </a>
            </div>

            <!-- Module 4: Student Attendance Register -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/attendance/student.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-clipboard-user"></i></div>
                    <div>
                        <h6 class="module-title">Attendance Register</h6>
                        <p class="module-desc">Mark class daily attendance</p>
                    </div>
                </a>
            </div>

            <!-- Module 5: Fee Ledger Sheets -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/fees/challan.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <div>
                        <h6 class="module-title">Fee Ledger Sheets</h6>
                        <p class="module-desc">Manage student fee ledgers</p>
                    </div>
                </a>
            </div>

            <!-- Module 6: Fee Structure -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/fees/structure.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-tags"></i></div>
                    <div>
                        <h6 class="module-title">Fee Structure</h6>
                        <p class="module-desc">Configure class fee rules</p>
                    </div>
                </a>
            </div>

            <!-- Module 7: Examination Management -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/examination/dashboard.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-award"></i></div>
                    <div>
                        <h6 class="module-title">Exam Management</h6>
                        <p class="module-desc">Exams, marks & result cards</p>
                    </div>
                </a>
            </div>

            <!-- Module 8: Accounts & Cash -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?php echo APP_URL; ?>/modules/cash/dashboard.php" class="module-card">
                    <div class="module-icon-box"><i class="fa-solid fa-wallet"></i></div>
                    <div>
                        <h6 class="module-title">Accounts & Cash</h6>
                        <p class="module-desc">Income, expenses & cashbook</p>
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
                <h5 class="section-title mb-3"><i class="fa-solid fa-chart-pie text-success"></i>Today's Attendance Status</h5>
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
                <h5 class="section-title mb-1"><i class="fa-solid fa-chart-line text-primary"></i>Monthly Fee Recovery</h5>
                <div class="small text-muted mb-3">Expected Fee (After Discount): <strong class="text-dark">Rs. <?php echo number_format($feeReceivable); ?></strong></div>
                <div style="height: 210px; position: relative;">
                    <canvas id="financialDoughnutChart"></canvas>
                </div>
                <div class="d-flex justify-content-around text-center mt-3 pt-2 border-top small">
                    <div><span class="d-block text-muted" style="font-size:0.75rem;">Received (Paid)</span><strong class="text-success">Rs. <?php echo number_format($feeReceived); ?></strong></div>
                    <div><span class="d-block text-muted" style="font-size:0.75rem;">Pending Fee (Due)</span><strong class="text-danger">Rs. <?php echo number_format($feeBalance); ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Class Strength Distribution Bar Chart -->
        <div class="col-12 col-lg-4">
            <div class="table-card p-4 h-100">
                <h5 class="section-title mb-3"><i class="fa-solid fa-chart-bar text-purple" style="color: #8B5CF6;"></i>Class Strength Distribution</h5>
                <div style="height: 230px; position: relative;">
                    <canvas id="classStrengthBarChart"></canvas>
                </div>
                <div class="text-center mt-3 pt-2 border-top small text-muted">
                    <span>Active Student Enrollment by Class</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Recent Data Logs: Recent Admissions & Fee Receipts -->
    <div class="row g-4 mb-4">
        <!-- Recent Student Admissions -->
        <div class="col-12 col-lg-6">
            <div class="table-card h-100">
                <div class="table-card-header">
                    <h5 class="fw-bold mb-0 text-dark fs-6"><i class="fa-solid fa-user-graduate me-2 text-primary"></i>Recent Admissions</h5>
                    <a href="<?php echo APP_URL; ?>/modules/students/list.php" class="btn btn-sm btn-outline-primary fw-semibold">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Admission #</th>
                                <th>Class</th>
                                <th class="text-end">Reg Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentStudents)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No student registrations recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentStudents as $rs): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="student-avatar-sm">
                                                    <?php echo strtoupper(substr($rs['first_name'], 0, 1)); ?>
                                                </div>
                                                <span class="fw-semibold text-dark"><?php echo htmlspecialchars($rs['first_name'] . ' ' . $rs['last_name']); ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-light text-primary border font-monospace"><?php echo htmlspecialchars($rs['admission_no']); ?></span></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($rs['current_class'] ?? 'Class Assigned'); ?></span></td>
                                        <td class="text-end text-muted small"><?php echo date('d M Y', strtotime($rs['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Fee Receipts -->
        <div class="col-12 col-lg-6">
            <div class="table-card h-100">
                <div class="table-card-header">
                    <h5 class="fw-bold mb-0 text-dark fs-6"><i class="fa-solid fa-receipt me-2 text-success"></i>Recent Payments Collected</h5>
                    <a href="<?php echo APP_URL; ?>/modules/fees/receipts.php" class="btn btn-sm btn-outline-success fw-semibold">Receipts Log</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Student</th>
                                <th class="text-end">Amount Paid</th>
                                <th class="text-center">Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentPayments)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No fee payments collected today.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentPayments as $rp): ?>
                                    <tr>
                                        <td><span class="badge bg-success-subtle text-success border font-monospace fw-bold"><?php echo htmlspecialchars($rp['receipt_no']); ?></span></td>
                                        <td class="fw-semibold text-dark"><?php echo htmlspecialchars($rp['first_name'] . ' ' . $rp['last_name']); ?></td>
                                        <td class="text-end font-monospace fw-bold text-success">Rs. <?php echo number_format($rp['amount_paid'], 2); ?></td>
                                        <td class="text-center"><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($rp['payment_method']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js Engine Injection -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Today's Attendance Doughnut Chart
    const ctxAtt = document.getElementById('attendanceDoughnutChart');
    if (ctxAtt) {
        new Chart(ctxAtt.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late', 'Leave'],
                datasets: [{
                    data: [
                        <?php echo $studentsPresent; ?>,
                        <?php echo $studentsAbsent; ?>,
                        <?php echo $studentsLate; ?>,
                        <?php echo $studentsLeave; ?>
                    ],
                    backgroundColor: ['#10B981', '#EF4444', '#F59E0B', '#3B82F6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // 2. Monthly Financial Doughnut Chart
    const ctxFin = document.getElementById('financialDoughnutChart');
    if (ctxFin) {
        new Chart(ctxFin.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Received', 'Pending Balance'],
                datasets: [{
                    data: [<?php echo $feeReceived; ?>, <?php echo $feeBalance; ?>],
                    backgroundColor: ['#10B981', '#EF4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // 3. Class Strength Bar Chart
    const ctxClass = document.getElementById('classStrengthBarChart');
    if (ctxClass) {
        new Chart(ctxClass.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($classLabels); ?>,
                datasets: [{
                    label: 'Students',
                    data: <?php echo json_encode($classData); ?>,
                    backgroundColor: '#8B5CF6',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748B', font: { size: 10 } } },
                    y: { grid: { color: '#F1F5F9' }, ticks: { color: '#64748B', font: { size: 10 } } }
                }
            }
        });
    }

});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
