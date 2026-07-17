<?php
/**
 * Indus Grammar School ERP - Consolidated Examination Reports Console
 * Version 4.0.0
 */

$pageTitle = 'Examination Reports';
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

// Selectors
$examTypes = $db->query("SELECT * FROM exam_types WHERE status = 'Active' ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$classes   = SchoolClass::all();
$sessions  = $db->query("SELECT DISTINCT academic_session FROM exam_types UNION SELECT '" . CURRENT_ACADEMIC_YEAR . "'")->fetchAll(PDO::FETCH_COLUMN);

// Filter values
$selectedReport = sanitize($_GET['report_type'] ?? 'result_summary');
$selectedExam   = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass  = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedSession = sanitize($_GET['academic_session'] ?? CURRENT_ACADEMIC_YEAR);

$reportTitle = "Examination Report";
$reportData = [];

try {
    switch ($selectedReport) {
        
        case 'result_summary':
            $reportTitle = "Result Pass/Fail Summary";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT c.class_name, c.section,
                           SUM(CASE WHEN er.status = 'Pass' THEN 1 ELSE 0 END) as passed,
                           SUM(CASE WHEN er.status = 'Fail' THEN 1 ELSE 0 END) as failed,
                           COUNT(er.id) as total_graded
                    FROM exam_results er
                    JOIN classes c ON er.class_id = c.id
                    WHERE er.exam_type_id = :etid
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND er.class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                $sql .= " GROUP BY c.id ORDER BY c.class_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'subject_wise':
            $reportTitle = "Subject-Wise Performance Analysis";
            if ($selectedExam > 0 && $selectedClass > 0) {
                $stmt = $db->prepare("
                    SELECT s.subject_name, s.subject_code, s.total_marks, s.passing_marks,
                           COUNT(sm.id) as total_papers,
                           SUM(CASE WHEN sm.status = 'Present' AND sm.marks_obtained >= s.passing_marks THEN 1 ELSE 0 END) as passed,
                           SUM(CASE WHEN sm.status = 'Present' AND sm.marks_obtained < s.passing_marks THEN 1 ELSE 0 END) as failed,
                           MAX(sm.marks_obtained) as highest_score,
                           AVG(sm.marks_obtained) as average_score
                    FROM subjects s
                    LEFT JOIN student_marks sm ON sm.subject_id = s.id AND sm.exam_type_id = :etid
                    WHERE s.class_id = :cid AND s.status = 'Active'
                    GROUP BY s.id
                    ORDER BY s.subject_name ASC
                ");
                $stmt->execute(['etid' => $selectedExam, 'cid' => $selectedClass]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'class_wise':
            $reportTitle = "Class-Wise Complete Roster Sheet";
            if ($selectedExam > 0 && $selectedClass > 0) {
                // Fetch students and their full results
                $stmt = $db->prepare("
                    SELECT st.first_name, st.last_name, st.admission_no,
                           er.total_marks, er.obtained_marks, er.percentage, er.grade, er.position, er.status
                    FROM students st
                    LEFT JOIN exam_results er ON er.student_id = st.id AND er.exam_type_id = :etid
                    WHERE st.class_id = :cid AND st.status = 'Active'
                    ORDER BY er.position ASC, st.first_name ASC
                ");
                $stmt->execute(['etid' => $selectedExam, 'cid' => $selectedClass]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'pass_fail':
            $reportTitle = "Student Pass/Fail Status List";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT er.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
                    FROM exam_results er
                    JOIN students st ON er.student_id = st.id
                    JOIN classes c ON er.class_id = c.id
                    WHERE er.exam_type_id = :etid
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND er.class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                $sql .= " ORDER BY c.class_name ASC, er.position ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'top_positions':
            $reportTitle = "Top Merit Achievers Report";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT p.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
                    FROM positions p
                    JOIN students st ON p.student_id = st.id
                    JOIN classes c ON p.class_id = c.id
                    WHERE p.exam_type_id = :etid AND p.position_no <= 3
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND p.class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                $sql .= " ORDER BY c.class_name ASC, p.position_no ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'failure_report':
            $reportTitle = "Student Failure Records Report";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT er.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
                    FROM exam_results er
                    JOIN students st ON er.student_id = st.id
                    JOIN classes c ON er.class_id = c.id
                    WHERE er.exam_type_id = :etid AND er.status = 'Fail'
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND er.class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                $sql .= " ORDER BY c.class_name ASC, st.first_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'promotions_report':
            $reportTitle = "Academic Promotion Registry Journal ($selectedSession)";
            $reportData = Promotion::all($selectedSession);
            break;
    }
} catch (Exception $e) {
    error_log("Reports load error: " . $e->getMessage());
}
?>

<!-- Title Header -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-line text-primary me-2"></i>Examination Reports</h3>
        <p class="text-muted small mb-0">Generate class summaries, merit rankings, pass rates, and promotions registry records.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="dashboard.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="reportForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Report Type</label>
                <select class="form-select" name="report_type" id="reportSelect" onchange="toggleFilterFields()">
                    <option value="result_summary" <?php echo $selectedReport === 'result_summary' ? 'selected' : ''; ?>>Result Summary</option>
                    <option value="subject_wise" <?php echo $selectedReport === 'subject_wise' ? 'selected' : ''; ?>>Subject Wise Result</option>
                    <option value="class_wise" <?php echo $selectedReport === 'class_wise' ? 'selected' : ''; ?>>Class Wise Result</option>
                    <option value="pass_fail" <?php echo $selectedReport === 'pass_fail' ? 'selected' : ''; ?>>Pass/Fail Report</option>
                    <option value="top_positions" <?php echo $selectedReport === 'top_positions' ? 'selected' : ''; ?>>Top Position Report</option>
                    <option value="failure_report" <?php echo $selectedReport === 'failure_report' ? 'selected' : ''; ?>>Failure Report</option>
                    <option value="promotions_report" <?php echo $selectedReport === 'promotions_report' ? 'selected' : ''; ?>>Promotion Report</option>
                </select>
            </div>

            <div class="col-md-3 filter-field" id="examField">
                <label class="form-label small fw-semibold text-muted">Exam Term</label>
                <select class="form-select" name="exam_type_id">
                    <option value="0">All Exams</option>
                    <?php foreach ($examTypes as $et): ?>
                        <option value="<?php echo $et['id']; ?>" <?php echo $selectedExam === (int)$et['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($et['exam_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 filter-field" id="classField">
                <label class="form-label small fw-semibold text-muted">Class & Section</label>
                <select class="form-select" name="class_id">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 filter-field" id="sessionField" style="display:none;">
                <label class="form-label small fw-semibold text-muted">Session</label>
                <select class="form-select" name="academic_session">
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $selectedSession === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Generate</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Output Area -->
<div class="card border-0 shadow-sm mb-5" style="border-radius:12px;" id="reportPrintArea">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-1 text-secondary" id="reportTitle"><?php echo htmlspecialchars($reportTitle); ?></h5>
            <small class="text-muted d-print-none">Generated: <?php echo date('d-M-Y H:i'); ?></small>
        </div>
        <div class="d-print-none">
            <button class="btn btn-outline-secondary btn-sm px-3 me-2" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print</button>
            <button class="btn btn-outline-success btn-sm px-3" onclick="exportToExcel()"><i class="fa-solid fa-file-excel me-1"></i>Export Excel</button>
        </div>
    </div>

    <div class="card-body p-4 pt-2">
        <div class="table-responsive">
            <table class="table table-bordered custom-table align-middle" id="reportDataTable">
                
                <!-- 1. RESULT SUMMARY TABLE -->
                <?php if ($selectedReport === 'result_summary'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Class Section</th>
                            <th class="text-center">Total Graded Students</th>
                            <th class="text-center text-success">Passed</th>
                            <th class="text-center text-danger">Failed</th>
                            <th class="text-center">Class Pass Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted small">No summaries compiled. Select an exam term and click Generate.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $rate = $row['total_graded'] > 0 ? ($row['passed'] / $row['total_graded']) * 100 : 0.00;
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td class="text-center fw-bold text-muted"><?php echo $row['total_graded']; ?></td>
                                <td class="text-center fw-bold text-success"><?php echo $row['passed']; ?></td>
                                <td class="text-center fw-bold text-danger"><?php echo $row['failed']; ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($rate, 1); ?>%</td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 2. SUBJECT WISE RESULT TABLE -->
                <?php elseif ($selectedReport === 'subject_wise'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Subject</th>
                            <th class="text-center">Total Papers</th>
                            <th class="text-center text-success">Passed</th>
                            <th class="text-center text-danger">Failed</th>
                            <th class="text-end">Highest Score</th>
                            <th class="text-end">Average Score</th>
                            <th class="text-center">Pass Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted small">Choose an exam and class section to populate subject analysis.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $rate = $row['total_papers'] > 0 ? ($row['passed'] / $row['total_papers']) * 100 : 0.00;
                            ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <?php echo htmlspecialchars($row['subject_name']); ?>
                                    <small class="text-muted d-block text-xs"><?php echo htmlspecialchars($row['subject_code']); ?></small>
                                </td>
                                <td class="text-center fw-semibold"><?php echo $row['total_papers']; ?></td>
                                <td class="text-center text-success fw-bold"><?php echo $row['passed']; ?></td>
                                <td class="text-center text-danger fw-bold"><?php echo $row['failed']; ?></td>
                                <td class="text-end fw-bold text-dark"><?php echo $row['highest_score'] !== null ? number_format($row['highest_score'], 1) . ' / ' . $row['total_marks'] : '—'; ?></td>
                                <td class="text-end fw-bold text-muted"><?php echo $row['average_score'] !== null ? number_format($row['average_score'], 1) . ' / ' . $row['total_marks'] : '—'; ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($rate, 1); ?>%</td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 3. CLASS WISE RESULT TABLE -->
                <?php elseif ($selectedReport === 'class_wise'): ?>
                    <thead>
                        <tr class="table-light">
                            <th width="80" class="text-center">Rank</th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th class="text-end">Obtained Marks</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted small">Choose an exam and class section to populate the full roster.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="text-center fw-bold"><?php echo $row['position'] ? '#' . $row['position'] : '—'; ?></td>
                                <td><code class="text-muted"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-end fw-bold text-dark"><?php echo $row['obtained_marks'] !== null ? number_format($row['obtained_marks'], 1) . ' / ' . number_format($row['total_marks'], 0) : '—'; ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo $row['percentage'] !== null ? number_format($row['percentage'], 1) . '%' : '—'; ?></td>
                                <td class="text-center fw-bold text-secondary fs-6"><?php echo htmlspecialchars($row['grade'] ?: '—'); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Pass' ? 'success' : 'danger'; ?>-soft px-3 py-1 rounded-pill fw-bold"><?php echo $row['status'] ?: 'Fail'; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 4. PASS/FAIL REPORT TABLE -->
                <?php elseif ($selectedReport === 'pass_fail'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Class Section</th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th class="text-center">Rank</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted small">Choose an exam term to load pass/fail roster.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td><code class="text-muted"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center fw-semibold"><?php echo $row['position'] ? '#' . $row['position'] : '—'; ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($row['percentage'], 1); ?>%</td>
                                <td class="text-center fw-bold"><?php echo htmlspecialchars($row['grade']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Pass' ? 'success' : 'danger'; ?>-soft px-3 py-1 rounded-pill fw-bold"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 5. TOP POSITION REPORT TABLE -->
                <?php elseif ($selectedReport === 'top_positions'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Class Section</th>
                            <th class="text-center">Position</th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted small">Select an exam term to view top merit holders.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td class="text-center">
                                    <?php 
                                        $rank = (int)$row['position_no'];
                                        if ($rank === 1) echo '<span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold">1st</span>';
                                        else if ($rank === 2) echo '<span class="badge bg-secondary text-white px-3 py-1 rounded-pill fw-bold">2nd</span>';
                                        else if ($rank === 3) echo '<span class="badge bg-danger text-white px-3 py-1 rounded-pill">3rd</span>';
                                    ?>
                                </td>
                                <td><code class="text-muted"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($row['percentage'], 1); ?>%</td>
                                <td class="text-center fw-bold"><?php echo htmlspecialchars($row['grade']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Pass' ? 'success' : 'danger'; ?>-soft px-3 py-1 rounded-pill fw-bold"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 6. FAILURE REPORT TABLE -->
                <?php elseif ($selectedReport === 'failure_report'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Class Section</th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th class="text-center">Obtained Percentage</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted small">No failures recorded or no exam term selected.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td><code class="text-muted"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-danger"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($row['percentage'], 1); ?>%</td>
                                <td class="text-center fw-bold fs-6 text-danger"><?php echo htmlspecialchars($row['grade']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-danger-soft px-3 py-1 rounded-pill fw-bold">Fail</span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 7. PROMOTIONS REPORT TABLE -->
                <?php elseif ($selectedReport === 'promotions_report'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th class="text-center">From Class</th>
                            <th class="text-center">To Class</th>
                            <th class="text-center">Promotion Date</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted small">No student promotions logs found.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td><code class="text-muted"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center"><span class="badge bg-light text-muted border"><?php echo htmlspecialchars($row['from_class'] . ' - ' . $row['from_section']); ?></span></td>
                                <td class="text-center"><span class="badge bg-primary text-white"><?php echo htmlspecialchars($row['to_class'] . ' - ' . $row['to_section']); ?></span></td>
                                <td class="text-center small"><?php echo date('d-M-Y', strtotime($row['promotion_date'])); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success-soft px-3 py-1 rounded-pill fw-bold"><?php echo htmlspecialchars($row['status']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                <?php endif; ?>

            </table>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #reportPrintArea, #reportPrintArea * {
        visibility: visible;
    }
    #reportPrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 0 !important;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #ddd !important;
        padding: 8px !important;
        font-size: 11px !important;
    }
}
</style>

<?php $extraJS = '<script>
function toggleFilterFields() {
    const reportType = document.getElementById("reportSelect").value;
    const examField = document.getElementById("examField");
    const classField = document.getElementById("classField");
    const sessionField = document.getElementById("sessionField");

    // Default states
    examField.style.display = "block";
    classField.style.display = "block";
    sessionField.style.display = "none";

    if (reportType === "promotions_report") {
        examField.style.display = "none";
        classField.style.display = "none";
        sessionField.style.display = "block";
    }
}

function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = document.getElementById("reportTitle").innerText.replace(/\s+/g, "_").toLowerCase() + ".xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.addEventListener("DOMContentLoaded", function() {
    toggleFilterFields();
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
