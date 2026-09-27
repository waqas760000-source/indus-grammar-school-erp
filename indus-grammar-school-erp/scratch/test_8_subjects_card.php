<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['email'] = 'admin@indusgrammar.edu.pk';
$_SESSION['role_id'] = 1;
$_SESSION['role_code'] = 'super_admin';
$_SESSION['role_name'] = 'Super Administrator';

require_once __DIR__ . '/../config/app.php';

echo "Testing 8 Subjects Report Card Layout...\n";

// Create 8 sample subjects array
$test8Marks = [
    ['subject_name' => 'English Language', 'subject_code' => 'ENG-101', 'total_marks' => 100, 'passing_marks' => 33, 'marks_obtained' => 88.5, 'remarks' => 'Excellent', 'status' => 'Present'],
    ['subject_name' => 'Mathematics', 'subject_code' => 'MATH-101', 'total_marks' => 100, 'passing_marks' => 33, 'marks_obtained' => 95.0, 'remarks' => 'Outstanding', 'status' => 'Present'],
    ['subject_name' => 'General Science (EVS)', 'subject_code' => 'SCI-101', 'total_marks' => 100, 'passing_marks' => 33, 'marks_obtained' => 82.0, 'remarks' => 'Very Good', 'status' => 'Present'],
    ['subject_name' => 'Urdu Literature', 'subject_code' => 'URD-101', 'total_marks' => 100, 'passing_marks' => 33, 'marks_obtained' => 76.5, 'remarks' => 'Good', 'status' => 'Present'],
    ['subject_name' => 'Islamiyat / Moral Studies', 'subject_code' => 'ISL-101', 'total_marks' => 100, 'passing_marks' => 33, 'marks_obtained' => 91.0, 'remarks' => 'Commendable', 'status' => 'Present'],
    ['subject_name' => 'Computer Science & IT', 'subject_code' => 'CS-101', 'total_marks' => 100, 'passing_marks' => 33, 'marks_obtained' => 89.0, 'remarks' => 'Proficient', 'status' => 'Present'],
    ['subject_name' => 'Social Studies (History/Geo)', 'subject_code' => 'SST-101', 'total_marks' => 100, 'passing_marks' => 33, 'marks_obtained' => 84.0, 'remarks' => 'Good', 'status' => 'Present'],
    ['subject_name' => 'General Knowledge & Current Affairs', 'subject_code' => 'GK-101', 'total_marks' => 100, 'passing_marks' => 33, 'marks_obtained' => 92.5, 'remarks' => 'Superior', 'status' => 'Present']
];

$studentInfo = Student::findById(1);
$activeExamTitle = "Annual Term 2026";
$activeClassTitle = "Class 8 - Section A";
$marks = $test8Marks;
$reportRemarks = ['attendance_percentage' => 96.5];

ob_start();
?>
<div class="result-card-wrapper" id="reportCardPrintArea">
    <div class="result-card-border-frame">
        <div class="result-card-inner">
            <div class="scholastic-section">
                <table class="scholastic-table">
                    <thead>
                        <tr class="scholastic-head-banner">
                            <th colspan="8">SCHOLASTIC AREA- <?php echo strtoupper(htmlspecialchars($activeExamTitle)); ?></th>
                        </tr>
                        <tr class="col-headers">
                            <th class="text-start" style="width: 25%;">Subjects</th>
                            <th style="width: 11%;">Subject Proficiency<br><small>(5)</small></th>
                            <th style="width: 11%;">Multi Assessment<br><small>(5)</small></th>
                            <th style="width: 11%;">Subject Enrichment<br><small>(5)</small></th>
                            <th style="width: 11%;">Portfolio<br><small>(5)</small></th>
                            <th style="width: 11%;">Term End<br><small>(80)</small></th>
                            <th style="width: 10%;">Total<br><small>(100)</small></th>
                            <th style="width: 10%;">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marks as $m): 
                            $maxM = (float)$m['total_marks'];
                            $obtM = (float)$m['marks_obtained'];
                            $subPct = ($obtM / $maxM) * 100;
                            $subGradeInfo = GradeSetup::getGradeByPercentage($subPct);
                        ?>
                            <tr>
                                <td class="text-start fw-bold"><?php echo htmlspecialchars($m['subject_name']); ?></td>
                                <td><?php echo number_format(($obtM/$maxM)*5, 1); ?></td>
                                <td><?php echo number_format(($obtM/$maxM)*5, 1); ?></td>
                                <td><?php echo number_format(($obtM/$maxM)*5, 1); ?></td>
                                <td><?php echo number_format(($obtM/$maxM)*5, 1); ?></td>
                                <td><?php echo number_format(($obtM/$maxM)*80, 1); ?></td>
                                <td class="fw-bold text-primary"><?php echo number_format($obtM, 1); ?></td>
                                <td class="fw-bold"><?php echo $subGradeInfo['grade']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
$html = ob_get_clean();
echo "Rendered 8 Subjects Scholastic Table successfully! Size: " . strlen($html) . " bytes.\n";

