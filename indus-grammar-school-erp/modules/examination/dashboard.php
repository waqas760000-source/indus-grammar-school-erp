<?php
/**
 * Indus Grammar School ERP - Examination Dashboard
 * Version 4.0.0
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

// 1. Gather stats
$totalExams = (int)$db->query("SELECT COUNT(*) FROM exam_types")->fetchColumn();
$upcomingExams = (int)$db->query("SELECT COUNT(*) FROM exam_schedule WHERE exam_date > CURRENT_DATE()")->fetchColumn();
$completedExams = (int)$db->query("SELECT COUNT(*) FROM exam_schedule WHERE exam_date <= CURRENT_DATE()")->fetchColumn();
$resultsPublished = (int)$db->query("SELECT COUNT(DISTINCT exam_type_id, class_id) FROM exam_results")->fetchColumn();

$passedCount = (int)$db->query("SELECT COUNT(*) FROM exam_results WHERE status = 'Pass'")->fetchColumn();
$failedCount = (int)$db->query("SELECT COUNT(*) FROM exam_results WHERE status = 'Fail'")->fetchColumn();

$totalGraded = $passedCount + $failedCount;
$overallPassRate = $totalGraded > 0 ? round(($passedCount / $totalGraded) * 100, 1) : 0.00;

// Fetch upcoming schedules
$schedules = $db->query("
    SELECT es.*, et.exam_name, c.class_name, c.section, s.subject_name
    FROM exam_schedule es
    JOIN exam_types et ON es.exam_type_id = et.id
    JOIN classes c ON es.class_id = c.id
    JOIN subjects s ON es.subject_id = s.id
    ORDER BY es.exam_date ASC, es.start_time ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
:root {
    --royal-blue: #1e3a8a;
    --royal-blue-light: #3b82f6;
    --royal-blue-soft: #eff6ff;
}
.theme-card-blue {
    background: linear-gradient(135deg, var(--royal-blue) 0%, #2563eb 100%);
    color: white;
}
.submodule-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 12px;
    border: 1px solid rgba(226, 232, 240, 0.8);
}
.submodule-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.1);
    border-color: var(--royal-blue-light);
}
.submodule-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}
</style>

<!-- Header -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-file-signature me-2 text-primary"></i>Examination Dashboard</h3>
        <p class="text-muted small mb-0">Overview of exam terms, schedules, results metrics, student performance trackers, and academic promotions.</p>
    </div>
</div>

<!-- Key Stat Cards -->
<div class="row g-3 mb-4">
    <!-- Stat 1 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm theme-card-blue h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-white bg-opacity-20 p-3 rounded-3 me-3 text-white">
                    <i class="fa-solid fa-calendar-check fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-white text-opacity-75 small fw-semibold text-uppercase mb-1">Total Exams</h6>
                    <h3 class="fw-bold mb-0 text-white"><?php echo $totalExams; ?></h3>
                </div>
            </div>
        </div>
    </div>
    <!-- Stat 2 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-warning-soft p-3 rounded-3 me-3 text-warning">
                    <i class="fa-solid fa-clock fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Upcoming Exams</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $upcomingExams; ?></h3>
                </div>
            </div>
        </div>
    </div>
    <!-- Stat 3 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-success-soft p-3 rounded-3 me-3 text-success">
                    <i class="fa-solid fa-award fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Pass Percentage</h6>
                    <h3 class="fw-bold mb-0 text-success"><?php echo $overallPassRate; ?>%</h3>
                </div>
            </div>
        </div>
    </div>
    <!-- Stat 4 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-primary-soft p-3 rounded-3 me-3 text-primary">
                    <i class="fa-solid fa-square-poll-vertical fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Results Generated</h6>
                    <h3 class="fw-bold mb-0 text-primary"><?php echo $resultsPublished; ?> classes</h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Grid Section -->
<div class="row">
    <!-- Submodules Navigation List -->
    <div class="col-lg-8 mb-4">
        <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius:12px;">
            <h5 class="fw-bold text-secondary mb-4"><i class="fa-solid fa-grid-2 me-2 text-primary"></i>Examination Submodules</h5>
            
            <div class="row g-3">
                <!-- 1. Exam Types -->
                <div class="col-md-6 col-xl-4">
                    <div class="card submodule-card h-100">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="submodule-icon bg-primary-soft text-primary me-3">
                                <i class="fa-solid fa-calendar-days"></i>
                            </div>
                            <div>
                                <a href="exams.php" class="fw-bold text-dark text-decoration-none d-block">Exam Types</a>
                                <small class="text-muted text-xs">Configure exam terms</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 2. Exam Schedule -->
                <div class="col-md-6 col-xl-4">
                    <div class="card submodule-card h-100">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="submodule-icon bg-info-soft text-info me-3">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <div>
                                <a href="schedule.php" class="fw-bold text-dark text-decoration-none d-block">Exam Schedule</a>
                                <small class="text-muted text-xs">Time table planner</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Subjects -->
                <div class="col-md-6 col-xl-4">
                    <div class="card submodule-card h-100">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="submodule-icon bg-success-soft text-success me-3">
                                <i class="fa-solid fa-book-open"></i>
                            </div>
                            <div>
                                <a href="subjects.php" class="fw-bold text-dark text-decoration-none d-block">Subject Setup</a>
                                <small class="text-muted text-xs">Manage class curriculum</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Marks Entry -->
                <div class="col-md-6 col-xl-4">
                    <div class="card submodule-card h-100">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="submodule-icon bg-danger-soft text-danger me-3">
                                <i class="fa-solid fa-pen-nib"></i>
                            </div>
                            <div>
                                <a href="marks.php" class="fw-bold text-dark text-decoration-none d-block">Marks Entry</a>
                                <small class="text-muted text-xs">Record obtained scores</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Grade Setup -->
                <div class="col-md-6 col-xl-4">
                    <div class="card submodule-card h-100">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="submodule-icon bg-warning-soft text-warning-dark me-3">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                            <div>
                                <a href="grades.php" class="fw-bold text-dark text-decoration-none d-block">Grade Setup</a>
                                <small class="text-muted text-xs">Configure GPA thresholds</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Results -->
                <div class="col-md-6 col-xl-4">
                    <div class="card submodule-card h-100">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="submodule-icon bg-purple-soft text-purple me-3">
                                <i class="fa-solid fa-square-poll-vertical"></i>
                            </div>
                            <div>
                                <a href="results.php" class="fw-bold text-dark text-decoration-none d-block">Class Results</a>
                                <small class="text-muted text-xs">Process result registries</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 7. Report Cards -->
                <div class="col-md-6 col-xl-4">
                    <div class="card submodule-card h-100">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="submodule-icon bg-success-soft text-success me-3">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                            <div>
                                <a href="report_cards.php" class="fw-bold text-dark text-decoration-none d-block">Report Cards</a>
                                <small class="text-muted text-xs">Print student reports</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 8. Position List -->
                <div class="col-md-6 col-xl-4">
                    <div class="card submodule-card h-100">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="submodule-icon bg-warning-soft text-warning me-3">
                                <i class="fa-solid fa-list-ol"></i>
                            </div>
                            <div>
                                <a href="positions.php" class="fw-bold text-dark text-decoration-none d-block">Class Positions</a>
                                <small class="text-muted text-xs">Calculate merit ranks</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 9. Promotion Management -->
                <div class="col-md-6 col-xl-4">
                    <div class="card submodule-card h-100">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="submodule-icon bg-primary-soft text-primary me-3">
                                <i class="fa-solid fa-angles-up"></i>
                            </div>
                            <div>
                                <a href="promotions.php" class="fw-bold text-dark text-decoration-none d-block">Promotions</a>
                                <small class="text-muted text-xs">Promote to next class</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 10. Examination Reports -->
                <div class="col-md-6 col-xl-4 mx-auto">
                    <div class="card submodule-card h-100">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="submodule-icon bg-dark-soft text-dark me-3">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>
                            <div>
                                <a href="reports.php" class="fw-bold text-dark text-decoration-none d-block">Exam Reports</a>
                                <small class="text-muted text-xs">Consolidated analysis logs</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Upcoming Timetable -->
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:12px;">
            <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-clock-rotate-left me-2 text-warning"></i>Timetable Registry</h5>
            
            <?php if (empty($schedules)): ?>
                <div class="text-center py-5 text-muted small">No upcoming schedules compiled. Go to "Exam Schedule" to arrange a timetable.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($schedules as $s): ?>
                        <div class="list-group-item px-0 py-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-primary-soft text-primary rounded-pill small fw-semibold"><?php echo htmlspecialchars($s['class_name'] . '-' . $s['section']); ?></span>
                                <small class="text-muted fw-bold"><?php echo date('d M Y', strtotime($s['exam_date'])); ?></small>
                            </div>
                            <div class="fw-bold text-dark mt-2 mb-1"><?php echo htmlspecialchars($s['subject_name']); ?></div>
                            <div class="d-flex justify-content-between text-muted small">
                                <span><i class="fa-regular fa-clock me-1"></i><?php echo date('h:i A', strtotime($s['start_time'])); ?> - <?php echo date('h:i A', strtotime($s['end_time'])); ?></span>
                                <span>Room: <?php echo htmlspecialchars($s['room'] ?: '—'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
