<?php
/**
 * Indus Grammar School ERP - Consolidated Examination Reports Panel
 * Version 4.0.0
 */

$pageTitle = 'Examination Performance Reports';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$examTypes = $db->query("SELECT * FROM exam_types WHERE status = 'Active' ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$classes   = SchoolClass::all();

// Filter values
$selectedReport = sanitize($_GET['report_type'] ?? 'result_summary');
$selectedExam   = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass  = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$reportTitle = "Examination Report";
$reportData = [];

try {
    switch ($selectedReport) {
        
        case 'result_summary':
            $reportTitle = "Result Pass/Fail Summary Report";
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
            $reportTitle = "Subject-Wise Performance Distribution";
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
            $reportTitle = "Class Complete Grade Sheet Ledger";
            if ($selectedExam > 0 && $selectedClass > 0) {
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

        case 'pass_report':
            $reportTitle = "Passed Students List (Merit Stands)";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT er.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
                    FROM exam_results er
                    JOIN students st ON er.student_id = st.id
                    JOIN classes c ON er.class_id = c.id
                    WHERE er.exam_type_id = :etid AND er.status = 'Pass'
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

        case 'fail_report':
            $reportTitle = "Failed Students List (Defaulters Roster)";
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

        case 'top_positions':
            $reportTitle = "Merit Top Ranks Achievement Report";
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

        case 'grade_distribution':
            $reportTitle = "Grade Scales Count Breakdown";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT grade, COUNT(*) as student_count, AVG(percentage) as avg_percentage
                    FROM exam_results 
                    WHERE exam_type_id = :etid
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                $sql .= " GROUP BY grade ORDER BY avg_percentage DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
    }
} catch (Exception $e) {
    error_log("Exam reports error: " . $e->getMessage());
}

?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Exam Reports</h3>
        <p class="text-muted small mb-0">Consolidate subject averages, verify class position standings and check pass/fail rate distributions.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Ledger</button>
        <button class="btn btn-outline-success px-3 ms-2" onclick="exportToExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        <a href="dashboard.php" class="btn btn-outline-secondary px-3 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters Form -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Report Type</label>
                <select class="form-select" name="report_type" onchange="this.form.submit()">
                    <option value="result_summary" <?php echo $selectedReport === 'result_summary' ? 'selected' : ''; ?>>Result Summary</option>
                    <option value="subject_wise" <?php echo $selectedReport === 'subject_wise' ? 'selected' : ''; ?>>Subject Wise Result</option>
                    <option value="class_wise" <?php echo $selectedReport === 'class_wise' ? 'selected' : ''; ?>>Class Wise Result</option>
                    <option value="pass_report" <?php echo $selectedReport === 'pass_report' ? 'selected' : ''; ?>>Pass Report</option>
                    <option value="fail_report" <?php echo $selectedReport === 'fail_report' ? 'selected' : ''; ?>>Fail Report</option>
                    <option value="top_positions" <?php echo $selectedReport === 'top_positions' ? 'selected' : ''; ?>>Top Position List</option>
                    <option value="grade_distribution" <?php echo $selectedReport === 'grade_distribution' ? 'selected' : ''; ?>>Grade Distribution</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Exam Term</label>
                <select class="form-select" name="exam_type_id" required>
                    <option value="">— Choose Exam —</option>
                    <?php foreach ($examTypes as $et): ?>
                        <option value="<?php echo $et['id']; ?>" <?php echo $selectedExam === (int)$et['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($et['exam_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Class & Section</label>
                <select class="form-select" name="class_id">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Generate</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Output -->
<div class="card border-0 shadow-sm" style="border-radius:12px;" id="reportPrintArea">
    <!-- Print Header -->
    <div class="card-header bg-white border-0 pt-4 px-4 text-center d-none d-print-block border-bottom pb-3">
        <h3 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h3>
        <h5 class="text-secondary fw-semibold mb-1"><?php echo htmlspecialchars($reportTitle); ?></h5>
        <div class="text-muted small">
            Date: <?php echo date('d-M-Y H:i'); ?> | Generated By: <?php echo htmlspecialchars($_SESSION['username'] ?? 'ERP Admin'); ?>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                
                <!-- 1. SUMMARY -->
                <?php if ($selectedReport === 'result_summary'): ?>
                    <thead>
                        <tr>
                            <th>Class Section</th>
                            <th class="text-center">Total Graded Students</th>
                            <th class="text-center text-success">Passed</th>
                            <th class="text-center text-danger">Failed</th>
                            <th class="text-center">Passing Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No summaries compiled. Choose an exam term and click search.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $rate = $row['total_graded'] > 0 ? ($row['passed'] / $row['total_graded']) * 100 : 0.00;
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td class="text-center fw-semibold text-muted"><?php echo $row['total_graded']; ?></td>
                                <td class="text-center fw-bold text-success"><?php echo $row['passed']; ?></td>
                                <td class="text-center fw-bold text-danger"><?php echo $row['failed']; ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($rate, 1); ?>%</td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 2. SUBJECT PERFORMANCE -->
                <?php elseif ($selectedReport === 'subject_wise'): ?>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th class="text-center">Total Graded Papers</th>
                            <th class="text-center text-success">Passed</th>
                            <th class="text-center text-danger">Failed</th>
                            <th class="text-end">Highest Score</th>
                            <th class="text-end">Average Score</th>
                            <th class="text-center">Pass Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">Choose an exam and class section to display subject metrics.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $rate = $row['total_papers'] > 0 ? ($row['passed'] / $row['total_papers']) * 100 : 0.00;
                            ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <?php echo htmlspecialchars($row['subject_name']); ?>
                                    <small class="text-muted d-block text-xs"><?php echo htmlspecialchars($row['subject_code']); ?></small>
                                </td>
                                <td class="text-center text-muted fw-bold"><?php echo $row['total_papers']; ?> papers</td>
                                <td class="text-center text-success fw-bold"><?php echo $row['passed']; ?></td>
                                <td class="text-center text-danger fw-bold"><?php echo $row['failed']; ?></td>
                                <td class="text-end fw-bold text-dark"><?php echo $row['highest_score'] !== null ? number_format($row['highest_score'], 1) . ' / ' . $row['total_marks'] : '—'; ?></td>
                                <td class="text-end fw-semibold text-muted"><?php echo $row['average_score'] !== null ? number_format($row['average_score'], 1) . ' / ' . $row['total_marks'] : '—'; ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($rate, 1); ?>%</td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 3. CLASS WISE COMPLETE ROSTER -->
                <?php elseif ($selectedReport === 'class_wise'): ?>
                    <thead>
                        <tr>
                            <th class="text-center" width="80">Position</th>
                            <th>Admission No</th>
                            <th>Student</th>
                            <th class="text-end">Total Marks</th>
                            <th class="text-end">Obtained Marks</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">Choose exam term and class to load grade sheet rosters.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($row['status'] === 'Pass'): ?>
                                        <span class="badge bg-success-soft text-success px-3 py-1 rounded-pill fw-bold">Rank <?php echo $row['position']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border px-3 py-1 rounded-pill">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-end text-muted"><?php echo number_format($row['total_marks'], 0); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['obtained_marks'], 1); ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($row['percentage'], 1); ?>%</td>
                                <td class="text-center fw-bold fs-5 text-secondary"><?php echo htmlspecialchars($row['grade']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Pass' ? 'success' : 'danger'; ?>-soft px-3 py-1 rounded-pill fw-bold"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 4. PASS/FAIL REPORT LIST -->
                <?php elseif (in_array($selectedReport, ['pass_report', 'fail_report'])): ?>
                    <thead>
                        <tr>
                            <th>Class Section</th>
                            <th>Admission No</th>
                            <th>Student</th>
                            <th class="text-center">Rank</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No student records found matching this outcome.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center fw-semibold">Rank #<?php echo $row['position']; ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($row['percentage'], 1); ?>%</td>
                                <td class="text-center fw-bold fs-5 text-secondary"><?php echo htmlspecialchars($row['grade']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Pass' ? 'success' : 'danger'; ?>-soft px-3 py-1 rounded-pill fw-bold"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 5. TOP POSITIONS -->
                <?php elseif ($selectedReport === 'top_positions'): ?>
                    <thead>
                        <tr>
                            <th>Class Section</th>
                            <th class="text-center">Rank</th>
                            <th>Admission No</th>
                            <th>Student</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">Choose an exam and click Generate.</td></tr>
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
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($row['percentage'], 1); ?>%</td>
                                <td class="text-center fw-bold fs-5 text-secondary"><?php echo htmlspecialchars($row['grade']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 6. GRADE DISTRIBUTION -->
                <?php elseif ($selectedReport === 'grade_distribution'): ?>
                    <thead>
                        <tr>
                            <th>Grade Scale</th>
                            <th class="text-center">Students Count</th>
                            <th class="text-center">Average Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="3" class="text-center py-5 text-muted">No student distributions resolved.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($row['grade']); ?></td>
                                <td class="text-center fw-bold text-muted"><?php echo $row['student_count']; ?> students</td>
                                <td class="text-center fw-semibold text-primary"><?php echo number_format($row['avg_percentage'], 1); ?>%</td>
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
function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "' . strtolower(str_replace(' ', '_', $reportTitle)) . '_' . date('Ymd') . '.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
