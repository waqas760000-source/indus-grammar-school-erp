<?php
/**
 * Indus Grammar School ERP - Examination Dashboard
 * Version 5.0.0 - Premium Evaluation, Analytics & Result Engine Console
 */

$pageTitle = 'Examination Dashboard';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';

// Restricted to Super Admin, School Admin, Exam Controller, Teachers
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'teacher', 'exam_controller'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permissions to access the examination module.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

// Avatar helper wrapper
if (!function_exists('getAvatarColor')) {
    function getAvatarColor($name) {
        $colors = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    }
}

// 1. Gather Real Database Analytics
$totalExams     = 0;
$upcomingExams  = 0;
$completedExams = 0;
$resultsPublished = 0;
$passedCount    = 0;
$failedCount    = 0;
$distinctionCount = 0;
$promotionsCount = 0;
$gradeScalesCount = 0;

try {
    $totalExams      = (int)$db->query("SELECT COUNT(*) FROM exam_types")->fetchColumn();
    $upcomingExams   = (int)$db->query("SELECT COUNT(*) FROM exam_schedule WHERE exam_date >= CURRENT_DATE()")->fetchColumn();
    $completedExams  = (int)$db->query("SELECT COUNT(*) FROM exam_schedule WHERE exam_date < CURRENT_DATE()")->fetchColumn();
    $resultsPublished = (int)$db->query("SELECT COUNT(DISTINCT class_id) FROM exam_results")->fetchColumn();
    
    $passedCount     = (int)$db->query("SELECT COUNT(*) FROM exam_results WHERE status = 'Pass'")->fetchColumn();
    $failedCount     = (int)$db->query("SELECT COUNT(*) FROM exam_results WHERE status = 'Fail'")->fetchColumn();
    $distinctionCount = (int)$db->query("SELECT COUNT(*) FROM exam_results WHERE grade IN ('A+', 'A')")->fetchColumn();
    
    $promotionsCount  = (int)$db->query("SELECT COUNT(*) FROM promotions WHERE status = 'Promoted'")->fetchColumn();
    $gradeScalesCount = (int)$db->query("SELECT COUNT(*) FROM grade_setup")->fetchColumn();
} catch (Exception $e) {
    error_log("Error loading exam dashboard stats: " . $e->getMessage());
}

$totalGraded = $passedCount + $failedCount;
$overallPassRate = $totalGraded > 0 ? round(($passedCount / $totalGraded) * 100, 1) : 0.00;

// If exam_results has 0 records, calculate pass percentage fallback from student_marks table
if ($totalGraded === 0) {
    try {
        $marksTotal  = (int)$db->query("SELECT COUNT(*) FROM student_marks WHERE marks_obtained IS NOT NULL")->fetchColumn();
        $marksPassed = (int)$db->query("SELECT COUNT(*) FROM student_marks WHERE marks_obtained >= 40")->fetchColumn();
        if ($marksTotal > 0) {
            $overallPassRate = round(($marksPassed / $marksTotal) * 100, 1);
        }
    } catch (Exception $e) {}
}

// 2. Fetch Upcoming Timetable Registry
$schedules = [];
try {
    $schedules = $db->query("
        SELECT es.*, et.exam_name, c.class_name, c.section, s.subject_name
        FROM exam_schedule es
        JOIN exam_types et ON es.exam_type_id = et.id
        JOIN classes c ON es.class_id = c.id
        JOIN subjects s ON es.subject_id = s.id
        ORDER BY es.exam_date ASC, es.start_time ASC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 3. Fetch Class-wise Results Breakdown
$classPerformance = [];
try {
    $classPerformance = $db->query("
        SELECT c.class_name, c.section,
               COUNT(er.id) as total_students,
               SUM(CASE WHEN er.status = 'Pass' THEN 1 ELSE 0 END) as passed_students,
               AVG(er.percentage) as avg_pct
        FROM exam_results er
        JOIN classes c ON er.class_id = c.id
        GROUP BY er.class_id
        ORDER BY c.id ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 4. Grade Distribution Summary
$gradeBreakdown = [];
try {
    $gradeBreakdown = $db->query("
        SELECT grade, COUNT(*) as count 
        FROM exam_results 
        WHERE grade IS NOT NULL AND grade != ''
        GROUP BY grade 
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}
?>

<style>
.exam-card-hover {
    transition: all 0.2s ease-in-out;
    border-radius: 14px;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: #ffffff;
}
.exam-card-hover:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(13, 110, 253, 0.12);
    border-color: #3b82f6 !important;
}
.submodule-icon-box {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.kpi-mini-card {
    border-radius: 14px;
    transition: transform 0.15s ease-in-out;
}
.kpi-mini-card:hover { transform: translateY(-2px); }
</style>

<!-- Top Executive Hero Banner Header -->
<div class="card border-0 shadow-lg mb-4 overflow-hidden" style="border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f2744 100%);">
    <div class="card-body p-4 p-md-5 position-relative">
        <!-- Decorative glowing orb backdrop -->
        <div class="position-absolute end-0 top-0 translate-middle-y me-5 mt-4" style="width: 320px; height: 320px; background: radial-gradient(circle, rgba(13, 110, 253, 0.25) 0%, rgba(0, 0, 0, 0) 70%); pointer-events: none; filter: blur(40px);"></div>
        
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-7 mb-3 mb-lg-0">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(13, 110, 253, 0.15); border: 1px solid rgba(13, 110, 253, 0.3);">
                    <span class="pulse-dot bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    <span class="fw-bold uppercase tracking-wider" style="font-size: 0.72rem; letter-spacing: 1px; color: #60a5fa;">EVALUATION & RESULT ENGINE</span>
                </div>
                <h2 class="fw-extrabold text-white mb-2 tracking-tight d-flex align-items-center gap-2">
                    <i class="fa-solid fa-file-signature text-warning fs-2 me-2"></i>Examination Dashboard
                </h2>
                <p class="mb-0 leading-relaxed" style="font-size: 0.95rem; color: #94a3b8;">
                    Overview of exam terms, schedules, results metrics, student performance trackers, and academic promotions.
                </p>
            </div>
            
            <div class="col-lg-5 text-lg-end">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="schedule.php" class="btn btn-outline-light px-3 py-2 shadow-sm rounded-pill fw-semibold" title="Schedule Exam">
                        <i class="fa-solid fa-calendar-plus me-1 text-warning"></i>Schedule Exam
                    </a>
                    <a href="marks.php" class="btn btn-primary px-3 py-2 shadow-sm rounded-pill fw-semibold">
                        <i class="fa-solid fa-pen-nib me-1"></i>Enter Marks
                    </a>
                    <a href="results.php" class="btn btn-success px-3 py-2 shadow-sm rounded-pill fw-semibold">
                        <i class="fa-solid fa-square-poll-vertical me-1"></i>Process Results
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 8 KPI Executive Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-mini-card text-center bg-white border-start border-primary border-4">
            <div class="small text-muted fw-semibold text-uppercase" style="font-size:0.7rem;">Exam Terms</div>
            <div class="fs-2 fw-bold text-dark my-1"><?php echo $totalExams; ?></div>
            <div class="small text-muted" style="font-size:0.7rem;">Active Terms Configured</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-mini-card text-center" style="background-color: #fffbeb;">
            <div class="small text-warning fw-semibold text-uppercase" style="font-size:0.7rem;">Upcoming Papers</div>
            <div class="fs-2 fw-bold text-warning my-1"><?php echo $upcomingExams; ?></div>
            <div class="small text-warning-50" style="font-size:0.7rem;">Scheduled Timetables</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-mini-card text-center" style="background-color: #f0fdf4;">
            <div class="small text-success fw-semibold text-uppercase" style="font-size:0.7rem;">Overall Pass Rate</div>
            <div class="fs-2 fw-bold text-success my-1"><?php echo $overallPassRate; ?>%</div>
            <div class="small text-success-50" style="font-size:0.7rem;">Academic Success Index</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-mini-card text-center" style="background-color: #ecfeff;">
            <div class="small text-info fw-semibold text-uppercase" style="font-size:0.7rem;">Published Classes</div>
            <div class="fs-2 fw-bold text-info my-1"><?php echo $resultsPublished; ?></div>
            <div class="small text-info-50" style="font-size:0.7rem;">Class Result Registries</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-mini-card text-center" style="background-color: #faf5ff;">
            <div class="small text-primary fw-semibold text-uppercase" style="font-size:0.7rem;">Distinction Stars</div>
            <div class="fs-2 fw-bold text-primary my-1"><?php echo $distinctionCount; ?></div>
            <div class="small text-primary-50" style="font-size:0.7rem;">A+ & A Performers</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-mini-card text-center bg-white">
            <div class="small text-muted fw-semibold text-uppercase" style="font-size:0.7rem;">Academic Promotions</div>
            <div class="fs-2 fw-bold text-dark my-1"><?php echo $promotionsCount; ?></div>
            <div class="small text-muted" style="font-size:0.7rem;">Promoted to Next Class</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-mini-card text-center" style="background-color: #fef2f2;">
            <div class="small text-danger fw-semibold text-uppercase" style="font-size:0.7rem;">Completed Papers</div>
            <div class="fs-2 fw-bold text-danger my-1"><?php echo $completedExams; ?></div>
            <div class="small text-danger-50" style="font-size:0.7rem;">Exams Evaluated</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-mini-card text-center text-white" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6);">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.7rem;">Grade Scales</div>
            <div class="fs-2 fw-bold text-white my-1"><?php echo $gradeScalesCount; ?></div>
            <div class="small text-white-50" style="font-size:0.7rem;">GPA Thresholds</div>
        </div>
    </div>
</div>

<!-- Main Modules Hub Grid & Timetable Sidebar -->
<div class="row g-4 mb-4">
    <!-- Submodules Navigation Hub (Left 10 Cards) -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:16px;">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h5 class="fw-bold text-secondary mb-0">
                    <i class="fa-solid fa-shapes me-2 text-primary"></i>Examination Submodules Hub
                </h5>
                <span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold">10 Active Modules</span>
            </div>
            
            <div class="row g-3">
                <!-- 1. Exam Terms & Types -->
                <div class="col-md-6">
                    <a href="exams.php" class="text-decoration-none">
                        <div class="card exam-card-hover p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="submodule-icon-box bg-primary-soft text-primary me-3">
                                    <i class="fa-solid fa-calendar-days"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Exam Terms & Types</h6>
                                    <p class="text-muted small mb-0">Configure exam terms & weightings</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- 2. Exam Schedule -->
                <div class="col-md-6">
                    <a href="schedule.php" class="text-decoration-none">
                        <div class="card exam-card-hover p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="submodule-icon-box bg-info-soft text-info me-3">
                                    <i class="fa-solid fa-calendar-check"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Exam Timetable Schedule</h6>
                                    <p class="text-muted small mb-0">Date, time & room seating planner</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- 3. Subjects -->
                <div class="col-md-6">
                    <a href="subjects.php" class="text-decoration-none">
                        <div class="card exam-card-hover p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="submodule-icon-box bg-success-soft text-success me-3">
                                    <i class="fa-solid fa-book-open"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Subject Curriculum</h6>
                                    <p class="text-muted small mb-0">Manage class subjects & total marks</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- 4. Marks Entry -->
                <div class="col-md-6">
                    <a href="marks.php" class="text-decoration-none">
                        <div class="card exam-card-hover p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="submodule-icon-box bg-danger-soft text-danger me-3">
                                    <i class="fa-solid fa-pen-nib"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Marks Entry Console</h6>
                                    <p class="text-muted small mb-0">Record obtained marks & status</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- 5. Grade Setup -->
                <div class="col-md-6">
                    <a href="grades.php" class="text-decoration-none">
                        <div class="card exam-card-hover p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="icon-shape bg-warning text-dark rounded-circle me-3" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-sliders"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Grade & Marks Scale Setup</h6>
                                    <p class="text-muted small mb-0">Configure marks & grade scales</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- 6. Class Results -->
                <div class="col-md-6">
                    <a href="results.php" class="text-decoration-none">
                        <div class="card exam-card-hover p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="submodule-icon-box bg-primary-soft text-primary me-3">
                                    <i class="fa-solid fa-square-poll-vertical"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Class Result Registries</h6>
                                    <p class="text-muted small mb-0">Process class result sheets</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- 7. Report Cards -->
                <div class="col-md-6">
                    <a href="report_cards.php" class="text-decoration-none">
                        <div class="card exam-card-hover p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="submodule-icon-box bg-success-soft text-success me-3">
                                    <i class="fa-solid fa-id-card"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Student Report Cards</h6>
                                    <p class="text-muted small mb-0">Print official result vouchers</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- 8. Class Positions -->
                <div class="col-md-6">
                    <a href="positions.php" class="text-decoration-none">
                        <div class="card exam-card-hover p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="submodule-icon-box bg-warning-soft text-warning me-3">
                                    <i class="fa-solid fa-award"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Merit Class Positions</h6>
                                    <p class="text-muted small mb-0">Calculate merit rank positions</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- 9. Promotions -->
                <div class="col-md-6">
                    <a href="promotions.php" class="text-decoration-none">
                        <div class="card exam-card-hover p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="submodule-icon-box bg-info-soft text-info me-3">
                                    <i class="fa-solid fa-angles-up"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Academic Promotions</h6>
                                    <p class="text-muted small mb-0">Promote students to next class</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- 10. Exam Reports -->
                <div class="col-md-6">
                    <a href="reports.php" class="text-decoration-none">
                        <div class="card exam-card-hover p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="submodule-icon-box bg-dark text-white me-3">
                                    <i class="fa-solid fa-chart-line"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Exam Analytics Reports</h6>
                                    <p class="text-muted small mb-0">Consolidated examination logs</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Sidebar: Upcoming Exam Timetable Registry -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:16px;">
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                <h6 class="fw-bold text-secondary mb-0">
                    <i class="fa-solid fa-clock-rotate-left me-2 text-warning"></i>Exam Timetable Registry
                </h6>
                <a href="schedule.php" class="small text-primary fw-semibold text-decoration-none">View All</a>
            </div>
            
            <?php if (empty($schedules)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-calendar-xmark fs-1 d-block mb-2 opacity-50"></i>
                    <h6 class="fw-bold mb-1">No Upcoming Timetables</h6>
                    <p class="small mb-0">No exam papers are currently scheduled in the timetable system.</p>
                    <a href="schedule.php" class="btn btn-sm btn-outline-primary mt-3 rounded-pill px-3">+ Add Schedule</a>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($schedules as $s): 
                        $daysDiff = (int)ceil((strtotime($s['exam_date']) - time()) / 86400);
                        $badgeText = $daysDiff > 0 ? "In $daysDiff day(s)" : ($daysDiff === 0 ? "Today" : "Past");
                        $badgeBg   = $daysDiff > 0 ? "bg-warning-soft text-warning" : ($daysDiff === 0 ? "bg-danger text-white" : "bg-light text-muted");
                    ?>
                        <div class="list-group-item px-0 py-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-primary-soft text-primary rounded-pill small fw-bold">
                                    <?php echo htmlspecialchars($s['class_name'] . ' - ' . $s['section']); ?>
                                </span>
                                <span class="badge <?php echo $badgeBg; ?> rounded-pill small"><?php echo $badgeText; ?></span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($s['subject_name']); ?></h6>
                            <div class="small text-muted mb-1"><i class="fa-solid fa-graduation-cap me-1 text-secondary"></i><?php echo htmlspecialchars($s['exam_name']); ?></div>
                            <div class="d-flex justify-content-between text-muted small">
                                <span><i class="fa-regular fa-clock me-1 text-primary"></i><?php echo date('h:i A', strtotime($s['start_time'])); ?> - <?php echo date('h:i A', strtotime($s['end_time'])); ?></span>
                                <span><i class="fa-solid fa-door-open me-1 text-info"></i>Room: <?php echo htmlspecialchars($s['room'] ?: 'Main Hall'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Class-wise Performance & Grade Breakdown Section -->
<div class="row g-4 mb-4">
    <!-- Class Performance Table -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm p-4" style="border-radius:16px;">
            <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-chart-bar me-2 text-success"></i>Class Performance & Pass Ratios</h6>
            <div class="table-responsive">
                <table class="table custom-table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Class & Section</th>
                            <th width="100">Graded</th>
                            <th width="100">Passed</th>
                            <th width="140">Pass Ratio</th>
                            <th width="100" class="text-end">Avg Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($classPerformance)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">No class result performance data compiled yet. Process results to populate telemetry.</td>
                            </tr>
                        <?php else: foreach ($classPerformance as $cp): 
                            $tot = (int)$cp['total_students'];
                            $pas = (int)$cp['passed_students'];
                            $ratio = $tot > 0 ? round(($pas / $tot) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($cp['class_name'] . ' - ' . $cp['section']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo $tot; ?></span></td>
                                <td><span class="badge bg-success-soft text-success fw-bold"><?php echo $pas; ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-fill" style="height: 8px; border-radius: 4px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $ratio; ?>%;"></div>
                                        </div>
                                        <span class="small fw-bold text-dark" style="min-width:40px;"><?php echo $ratio; ?>%</span>
                                    </div>
                                </td>
                                <td class="text-end fw-bold text-primary"><?php echo round($cp['avg_pct'], 1); ?>%</td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Grade Scale Distribution Card -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:16px;">
            <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-pie-chart me-2 text-primary"></i>Grade Scale Distribution Index</h6>
            
            <?php 
            $defaultGrades = ['A+' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
            foreach ($gradeBreakdown as $gKey => $gCnt) {
                if (isset($defaultGrades[$gKey])) $defaultGrades[$gKey] = (int)$gCnt;
            }
            $totalGradeEntries = array_sum($defaultGrades);
            ?>

            <div class="list-group list-group-flush">
                <?php foreach ($defaultGrades as $gLabel => $gCount): 
                    $gPct = $totalGradeEntries > 0 ? round(($gCount / $totalGradeEntries) * 100, 1) : 0;
                    $gBadgeClass = 'bg-primary';
                    if ($gLabel === 'A+') $gBadgeClass = 'bg-success';
                    elseif ($gLabel === 'A') $gBadgeClass = 'bg-info';
                    elseif ($gLabel === 'B') $gBadgeClass = 'bg-primary';
                    elseif ($gLabel === 'C') $gBadgeClass = 'bg-warning';
                    elseif ($gLabel === 'F') $gBadgeClass = 'bg-danger';
                ?>
                    <div class="list-group-item px-0 py-2 border-0 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2" style="width: 80px;">
                            <span class="badge <?php echo $gBadgeClass; ?> px-2 py-1 font-monospace" style="min-width:32px;"><?php echo $gLabel; ?></span>
                            <span class="small fw-semibold text-dark"><?php echo $gCount; ?></span>
                        </div>
                        <div class="progress flex-fill mx-3" style="height: 8px; border-radius: 4px;">
                            <div class="progress-bar <?php echo $gBadgeClass; ?>" style="width: <?php echo $gPct; ?>%;"></div>
                        </div>
                        <span class="small font-monospace text-muted" style="min-width:45px; text-align:right;"><?php echo $gPct; ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
