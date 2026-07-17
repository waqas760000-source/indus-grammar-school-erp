<?php
/**
 * Indus Grammar School ERP - Dynamic Dashboard Page
 * Version 5.0.0 — Custom Redesign Matching Reference Sections
 */

$pageTitle = 'Dashboard';
$breadcrumbActive = 'Dashboard';
include_once __DIR__ . '/includes/header.php';

$currentUser = currentUser();
$userRole = $currentUser['role_code'] ?? '';

// Check dynamic values from MySQL
try {
    $db = Database::getConnection();
    $currentMonth = (int)date('m');
    $currentYear = (int)date('Y');

    // 1. Student Strength
    $totalStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
    $totalBoys = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active' AND gender = 'Male'")->fetchColumn();
    $totalGirls = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active' AND gender = 'Female'")->fetchColumn();

    // 2. Student Attendance Today
    $studentsPresent = (int)$db->query("SELECT COUNT(*) FROM attendance WHERE date = CURRENT_DATE AND status = 'Present'")->fetchColumn();
    $studentsAbsent = (int)$db->query("SELECT COUNT(*) FROM attendance WHERE date = CURRENT_DATE AND status = 'Absent'")->fetchColumn();
    $presentBoys = (int)$db->query("SELECT COUNT(a.id) FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.date = CURRENT_DATE AND a.status = 'Present' AND s.gender = 'Male'")->fetchColumn();
    $presentGirls = (int)$db->query("SELECT COUNT(a.id) FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.date = CURRENT_DATE AND a.status = 'Present' AND s.gender = 'Female'")->fetchColumn();
    
    $totalMarkedToday = $studentsPresent + $studentsAbsent;
    $studentPresentPct = $totalMarkedToday > 0 ? round(($studentsPresent / $totalMarkedToday) * 100, 1) : 0;
    $studentAbsentPct = $totalMarkedToday > 0 ? round(($studentsAbsent / $totalMarkedToday) * 100, 1) : 0;

    // 3. Staff
    $totalStaff = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active'")->fetchColumn();
    $maleStaff = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active' AND gender = 'Male'")->fetchColumn();
    $femaleStaff = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active' AND gender = 'Female'")->fetchColumn();

    // 4. Staff Attendance Today
    $staffPresent = (int)$db->query("SELECT COUNT(*) FROM staff_attendance WHERE date = CURRENT_DATE AND status = 'Present'")->fetchColumn();
    $staffAbsent = (int)$db->query("SELECT COUNT(*) FROM staff_attendance WHERE date = CURRENT_DATE AND status = 'Absent'")->fetchColumn();
    $maleStaffPresent = (int)$db->query("SELECT COUNT(sa.id) FROM staff_attendance sa JOIN staff s ON sa.staff_id = s.id WHERE sa.date = CURRENT_DATE AND sa.status = 'Present' AND s.gender = 'Male'")->fetchColumn();
    $femaleStaffPresent = (int)$db->query("SELECT COUNT(sa.id) FROM staff_attendance sa JOIN staff s ON sa.staff_id = s.id WHERE sa.date = CURRENT_DATE AND sa.status = 'Present' AND s.gender = 'Female'")->fetchColumn();
    
    $totalStaffMarked = $staffPresent + $staffAbsent;
    $staffPresentPct = $totalStaffMarked > 0 ? round(($staffPresent / $totalStaffMarked) * 100, 1) : 0;
    $staffAbsentPct = $totalStaffMarked > 0 ? round(($staffAbsent / $totalStaffMarked) * 100, 1) : 0;

    // 5. Families
    $totalFamilies = (int)$db->query("SELECT COUNT(DISTINCT guardian_phone) FROM students WHERE status = 'Active'")->fetchColumn();
    $singleChildFamilies = (int)$db->query("SELECT COUNT(*) FROM (SELECT guardian_phone FROM students WHERE status = 'Active' GROUP BY guardian_phone HAVING COUNT(*) = 1) t")->fetchColumn();
    $multipleChildFamilies = (int)$db->query("SELECT COUNT(*) FROM (SELECT guardian_phone FROM students WHERE status = 'Active' GROUP BY guardian_phone HAVING COUNT(*) > 1) t")->fetchColumn();

    // 6. Current Month Fee Status
    $feeReceivable = (float)$db->query("SELECT COALESCE(SUM(total_payable), 0) FROM fee_ledger WHERE MONTH(due_date) = MONTH(CURRENT_DATE) AND YEAR(due_date) = YEAR(CURRENT_DATE)")->fetchColumn();
    $feeReceived = (float)$db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE) AND YEAR(payment_date) = YEAR(CURRENT_DATE)")->fetchColumn();
    $feeBalance = $feeReceivable - $feeReceived;
    $discountGiven = (float)$db->query("SELECT COALESCE(SUM(discount_amount), 0) FROM fee_ledger WHERE MONTH(due_date) = MONTH(CURRENT_DATE) AND YEAR(due_date) = YEAR(CURRENT_DATE)")->fetchColumn();
    $fineCollected = (float)$db->query("SELECT COALESCE(SUM(fine_amount), 0) FROM fee_ledger WHERE MONTH(due_date) = MONTH(CURRENT_DATE) AND YEAR(due_date) = YEAR(CURRENT_DATE) AND status = 'Paid'")->fetchColumn();
    $feeCollectionPct = $feeReceivable > 0 ? round(($feeReceived / $feeReceivable) * 100, 1) : 0;

    // 7. Current Month Arrear Status
    $arrearsReceivable = (float)$db->query("SELECT COALESCE(SUM(total_payable - paid_amount), 0) FROM fee_ledger WHERE due_date < DATE_FORMAT(CURRENT_DATE ,'%Y-%m-01') AND status IN ('Pending', 'Partial')")->fetchColumn();
    $arrearsReceived = (float)$db->query("SELECT COALESCE(SUM(fp.amount_paid), 0) FROM fee_payments fp JOIN fee_ledger fl ON fp.ledger_id = fl.id WHERE fl.due_date < DATE_FORMAT(CURRENT_DATE ,'%Y-%m-01') AND MONTH(fp.payment_date) = MONTH(CURRENT_DATE) AND YEAR(fp.payment_date) = YEAR(CURRENT_DATE)")->fetchColumn();
    $arrearsBalance = $arrearsReceivable - $arrearsReceived;
    $arrearsRecoveryPct = $arrearsReceivable > 0 ? round(($arrearsReceived / $arrearsReceivable) * 100, 1) : 0;

    // 8. Cash Summary
    $openingBalance = (float)$db->query("SELECT COALESCE(opening_balance, 0) FROM cash_register WHERE date = CURRENT_DATE LIMIT 1")->fetchColumn();
    $todayCollection = (float)$db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE payment_date = CURRENT_DATE")->fetchColumn();
    $todayExpenses = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = CURRENT_DATE")->fetchColumn();
    $cashInHand = $openingBalance + $todayCollection - $todayExpenses;
    $closingBalance = (float)$db->query("SELECT COALESCE(closing_balance, 0) FROM cash_register WHERE date = CURRENT_DATE AND status = 'Closed' LIMIT 1")->fetchColumn();

    // 9. Notices / Announcements / Exams
    require_once __DIR__ . '/models/Announcement.php';
    require_once __DIR__ . '/models/Circular.php';

    // Map role to target audience
    $targetAudience = 'Everyone';
    if ($userRole === 'student') {
        $targetAudience = 'Students';
    } elseif ($userRole === 'parent') {
        $targetAudience = 'Parents';
    } elseif ($userRole === 'teacher') {
        $targetAudience = 'Teachers';
    } elseif ($userRole === 'staff') {
        $targetAudience = 'Staff';
    }

    $activeAnnouncements = Announcement::activeForAudience($targetAudience);
    $activeCirculars = [];
    try {
        // If student is logged in, find their class
        $classId = 0;
        $progType = 'School';
        if ($userRole === 'student') {
            // Find student class
            $stmtClass = $db->prepare("SELECT class_id, academic_type FROM students WHERE id = (SELECT student_id FROM users WHERE id = :uid LIMIT 1)");
            $stmtClass->execute(['uid' => $_SESSION['user_id'] ?? 0]);
            $stRow = $stmtClass->fetch();
            if ($stRow) {
                $classId = (int)$stRow['class_id'];
                $progType = $stRow['academic_type'] ?: 'School';
            }
        }
        $activeCirculars = Circular::activeForUser($progType, $classId);
    } catch (Exception $e) {}

    $upcomingExams = $db->query("SELECT es.exam_date, e.exam_name, s.subject_name FROM exam_schedules es JOIN exams e ON es.exam_id = e.id JOIN subjects s ON es.subject_id = s.id WHERE es.exam_date >= CURRENT_DATE ORDER BY es.exam_date ASC LIMIT 3")->fetchAll();

    // 10. Recent Activities
    $activities = $db->query("SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 5")->fetchAll();

} catch (Exception $e) {
    die("Database Error loading Dashboard: " . $e->getMessage());
}
?>

<!-- Welcome Banner -->
<div class="row mb-5">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center bg-white p-4 shadow-sm border border-light" style="border-radius: 12px;">
            <div>
                <span class="text-muted small text-uppercase fw-semibold tracking-wider d-block mb-1">Welcome back,</span>
                <h2 class="fw-bold text-dark mb-1" style="font-size: 2.2rem;"><?php echo sanitize($currentUser['username'] === 'waqas7600' ? 'Waqas Ali' : ucfirst($currentUser['username'])); ?></h2>
                <p class="text-secondary mb-0">Indus Grammar School ERP Admin Desk.</p>
            </div>
            <div class="text-end d-none d-md-block">
                <span class="badge bg-primary px-3 py-2 rounded-pill mb-2"><i class="fa-solid fa-calendar me-2"></i>Academic Session</span>
                <h5 class="fw-semibold text-dark mb-0"><?php echo CURRENT_ACADEMIC_YEAR; ?></h5>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- ACADEMIC PANELS (Super Admin & School Admin) -->
<!-- ========================================== -->
<?php if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_SCHOOL_ADMIN): ?>
<div class="row g-4 mb-5">
    
    <!-- 1. Student Strength -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm bg-white p-4 h-100" style="border-radius: 12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-users text-primary me-2"></i>Student Strength</h5>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small fw-semibold">Total Students</span>
                    <span class="fw-bold fs-5 text-dark"><?php echo $totalStudents; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small">Total Boys</span>
                    <span class="fw-bold text-primary"><?php echo $totalBoys; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Total Girls</span>
                    <span class="fw-bold text-danger"><?php echo $totalGirls; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Student Attendance -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm bg-white p-4 h-100" style="border-radius: 12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-calendar-check text-success me-2"></i>Student Attendance</h5>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small fw-semibold">Present Students</span>
                    <span class="fw-bold text-dark"><?php echo $studentsPresent; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small">Present Boys / Girls</span>
                    <span class="fw-semibold small text-muted"><?php echo $presentBoys; ?> M / <?php echo $presentGirls; ?> F</span>
                </div>
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small">Present Rate</span>
                    <span class="badge bg-success-soft"><?php echo $studentPresentPct; ?>%</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Absent Rate</span>
                    <span class="badge bg-danger-soft"><?php echo $studentAbsentPct; ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Staff Summary -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm bg-white p-4 h-100" style="border-radius: 12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-users-gear text-info me-2"></i>Staff Details</h5>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small fw-semibold">Total Staff</span>
                    <span class="fw-bold text-dark"><?php echo $totalStaff; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small">Male Staff</span>
                    <span class="fw-semibold text-dark"><?php echo $maleStaff; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Female Staff</span>
                    <span class="fw-semibold text-dark"><?php echo $femaleStaff; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Staff Attendance -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm bg-white p-4 h-100" style="border-radius: 12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-user-clock text-warning me-2"></i>Staff Attendance</h5>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small fw-semibold">Present Staff</span>
                    <span class="fw-bold text-dark"><?php echo $staffPresent; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small">Male / Female Present</span>
                    <span class="small text-muted"><?php echo $maleStaffPresent; ?> M / <?php echo $femaleStaffPresent; ?> F</span>
                </div>
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small">Present %</span>
                    <span class="badge bg-success-soft"><?php echo $staffPresentPct; ?>%</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Absent %</span>
                    <span class="badge bg-danger-soft"><?php echo $staffAbsentPct; ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Families -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm bg-white p-4 h-100" style="border-radius: 12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-house-chimney text-primary me-2"></i>Families</h5>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small fw-semibold">Total Families</span>
                    <span class="fw-bold text-dark"><?php echo $totalFamilies; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <span class="text-muted small">Single Child Families</span>
                    <span class="fw-semibold text-dark"><?php echo $singleChildFamilies; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Multiple Child Families</span>
                    <span class="fw-semibold text-dark"><?php echo $multipleChildFamilies; ?></span>
                </div>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>

<!-- ========================================== -->
<!-- FINANCIAL PANELS (Super Admin & Accountant) -->
<!-- ========================================== -->
<?php if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_ACCOUNTANT): ?>
<div class="row g-4 mb-5">
    
    <!-- 6. Current Month Fee Status -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm bg-white p-4 h-100" style="border-radius: 12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Current Month Fee Status</h5>
            <table class="table table-sm table-borderless small mb-0">
                <tr class="border-bottom"><td class="text-muted py-2">Receivable:</td><td class="fw-bold text-dark py-2 text-end">Rs. <?php echo number_format($feeReceivable, 2); ?></td></tr>
                <tr class="border-bottom"><td class="text-muted py-2">Received:</td><td class="fw-bold text-success py-2 text-end">Rs. <?php echo number_format($feeReceived, 2); ?></td></tr>
                <tr class="border-bottom"><td class="text-muted py-2">Balance:</td><td class="fw-bold text-danger py-2 text-end">Rs. <?php echo number_format($feeBalance, 2); ?></td></tr>
                <tr class="border-bottom"><td class="text-muted py-2">Discount Given:</td><td class="fw-bold text-warning py-2 text-end">Rs. <?php echo number_format($discountGiven, 2); ?></td></tr>
                <tr class="border-bottom"><td class="text-muted py-2">Fine Collected:</td><td class="fw-bold text-info py-2 text-end">Rs. <?php echo number_format($fineCollected, 2); ?></td></tr>
                <tr><td class="text-muted py-2">Collection Pct:</td><td class="fw-bold text-primary py-2 text-end"><?php echo $feeCollectionPct; ?>%</td></tr>
            </table>
        </div>
    </div>

    <!-- 7. Current Month Arrear Status -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm bg-white p-4 h-100" style="border-radius: 12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-clock-rotate-left text-warning me-2"></i>Current Month Arrear Status</h5>
            <table class="table table-sm table-borderless small mb-0">
                <tr class="border-bottom"><td class="text-muted py-3">Arrears Receivable:</td><td class="fw-bold text-dark py-3 text-end">Rs. <?php echo number_format($arrearsReceivable, 2); ?></td></tr>
                <tr class="border-bottom"><td class="text-muted py-3">Arrears Received:</td><td class="fw-bold text-success py-3 text-end">Rs. <?php echo number_format($arrearsReceived, 2); ?></td></tr>
                <tr class="border-bottom"><td class="text-muted py-3">Arrears Balance:</td><td class="fw-bold text-danger py-3 text-end">Rs. <?php echo number_format($arrearsBalance, 2); ?></td></tr>
                <tr><td class="text-muted py-3">Recovery Percentage:</td><td class="fw-bold text-primary py-3 text-end"><?php echo $arrearsRecoveryPct; ?>%</td></tr>
            </table>
        </div>
    </div>

    <!-- 8. Cash Summary -->
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white p-4" style="border-radius: 12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-vault text-primary me-2"></i>Cash Summary</h5>
            <div class="row g-3 text-center">
                <div class="col-6 col-md-2 offset-md-1">
                    <div class="p-2 border rounded bg-light">
                        <span class="text-muted small d-block">Opening Balance</span>
                        <span class="fw-bold text-dark">Rs. <?php echo number_format($openingBalance); ?></span>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="p-2 border rounded bg-light">
                        <span class="text-muted small d-block">Today's Collection</span>
                        <span class="fw-bold text-success">Rs. <?php echo number_format($todayCollection); ?></span>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="p-2 border rounded bg-light">
                        <span class="text-muted small d-block">Today's Expenses</span>
                        <span class="fw-bold text-danger">Rs. <?php echo number_format($todayExpenses); ?></span>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="p-2 border rounded bg-light">
                        <span class="text-muted small d-block">Cash in Hand</span>
                        <span class="fw-bold text-primary">Rs. <?php echo number_format($cashInHand); ?></span>
                    </div>
                </div>
                <div class="col-12 col-md-2">
                    <div class="p-2 border rounded bg-light">
                        <span class="text-muted small d-block">Closing Balance</span>
                        <span class="fw-bold text-dark"><?php echo $closingBalance > 0 ? 'Rs. ' . number_format($closingBalance) : 'Not Closed'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>

<!-- ========================================== -->
<!-- NOTICE BOARD & EVENTS                      -->
<!-- ========================================== -->
<div class="row g-4 mb-5">
    
    <!-- 9. Notice Panel -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm bg-white p-4 h-100" style="border-radius: 12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-bullhorn text-danger me-2"></i>Notice Board & Announcements</h5>
            
            <div class="d-flex flex-column gap-3">
                <?php if (empty($activeAnnouncements) && empty($activeCirculars)): ?>
                    <div class="alert alert-light border small mb-0">No active notices or announcements.</div>
                <?php endif; ?>

                <!-- Announcements list -->
                <?php foreach ($activeAnnouncements as $ann): ?>
                    <div class="border-bottom border-light pb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark small">
                                <span class="badge bg-<?php echo $ann['priority'] === 'Urgent' ? 'danger' : 'warning'; ?>-soft text-xs me-1"><?php echo $ann['priority']; ?></span>
                                <?php echo htmlspecialchars($ann['title']); ?>
                            </span>
                            <span class="text-muted small" style="font-size:0.75rem;"><?php echo date('d M Y', strtotime($ann['start_date'])); ?></span>
                        </div>
                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($ann['content']); ?></p>
                    </div>
                <?php endforeach; ?>

                <!-- Circulars list -->
                <?php foreach ($activeCirculars as $circ): ?>
                    <div class="border-bottom border-light pb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-success small">
                                <i class="fa-solid fa-file-pdf me-1 text-danger"></i>[Circular] <?php echo htmlspecialchars($circ['title']); ?>
                            </span>
                            <span class="text-muted small" style="font-size:0.75rem;"><?php echo date('d M Y', strtotime($circ['issue_date'])); ?></span>
                        </div>
                        <p class="text-muted small mb-1"><?php echo htmlspecialchars($circ['description']); ?></p>
                        <?php if ($circ['attachment_path']): ?>
                            <a href="<?php echo htmlspecialchars($circ['attachment_path']); ?>" target="_blank" class="btn btn-xs btn-outline-danger px-3 py-1 rounded-pill small"><i class="fa-solid fa-download me-1"></i>Download PDF Notice</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 10. Upcoming Exams / Holidays -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm bg-white p-4 h-100" style="border-radius: 12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-calendar text-primary me-2"></i>Upcoming Exams & Events</h5>
            <ul class="list-group list-group-flush mb-0">
                <?php if (empty($upcomingExams)): ?>
                    <li class="list-group-item bg-transparent px-0 py-2 small text-muted">No exams scheduled in next 30 days.</li>
                <?php else: foreach ($upcomingExams as $exam): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                        <div>
                            <div class="fw-semibold text-dark small"><?php echo sanitize($exam['exam_name']); ?></div>
                            <div class="text-muted small" style="font-size:0.75rem;"><?php echo sanitize($exam['subject_name']); ?></div>
                        </div>
                        <span class="badge bg-dark rounded-pill"><?php echo date('d M', strtotime($exam['exam_date'])); ?></span>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>
    </div>

</div>

<!-- Recent Activities -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white p-4" style="border-radius: 12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i>Recent System Activities</h5>
            <div class="timeline d-flex flex-column gap-3">
                <?php foreach ($activities as $log): ?>
                    <div class="d-flex gap-3 align-items-start border-bottom pb-2">
                        <div class="bg-light text-primary p-2 rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                            <i class="fa-solid fa-circle-check fs-6 text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-dark small"><?php echo sanitize($log['action']); ?></div>
                            <div class="text-muted small mt-1"><?php echo sanitize($log['description']); ?></div>
                            <div class="text-muted small mt-1" style="font-size:0.7rem;"><i class="fa-regular fa-clock me-1"></i><?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?> by <strong><?php echo sanitize($log['username'] ?: 'System'); ?></strong></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
