<?php
/**
 * 1,000 Students Benchmark & Load Test Suite for Indus ERP
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Subject.php';
require_once __DIR__ . '/../models/ExamType.php';
require_once __DIR__ . '/../models/ExamResult.php';
require_once __DIR__ . '/../models/GradeSetup.php';
require_once __DIR__ . '/../models/Promotion.php';

// Prevent output buffering interference
ini_set('memory_limit', '512M');
set_time_limit(300);

echo "=========================================================\n";
echo " INDUS ERP - 1,000 STUDENTS PERFORMANCE & BENCHMARK SUITE \n";
echo "=========================================================\n\n";

$startTime = microtime(true);

try {
    $db = Database::getConnection();

    // 1. Setup Test Class & Exam Term if needed
    echo "[1/5] Setting up Test Class, Exam Term, and 6 Subjects...\n";
    $db->exec("INSERT INTO classes (class_name, section) VALUES ('Class 10', 'A') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $classId = (int)$db->lastInsertId();
    if ($classId <= 0) {
        $stmtC = $db->query("SELECT id FROM classes WHERE class_name='Class 10' AND section='A' LIMIT 1");
        $classId = (int)$stmtC->fetchColumn();
    }

    $examTypeId = ExamType::create([
        'exam_name' => 'Annual Exam Benchmark 2026',
        'academic_session' => '2025-2026',
        'academic_type' => 'School',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-20',
        'total_marks' => 600,
        'passing_percentage' => 40.00,
        'status' => 'Active'
    ]);
    if (!$examTypeId) {
        $stmtE = $db->query("SELECT id FROM exam_types WHERE exam_name='Annual Exam Benchmark 2026' LIMIT 1");
        $examTypeId = (int)$stmtE->fetchColumn();
    }

    $subjectNames = ['Mathematics', 'English', 'Science', 'Urdu', 'Computer', 'Islamiyat'];
    $subjectIds = [];
    foreach ($subjectNames as $code => $sname) {
        $sid = Subject::create([
            'class_id' => $classId,
            'subject_name' => $sname,
            'subject_code' => 'SUB-' . ($code + 1),
            'total_marks' => 100,
            'passing_marks' => 40,
            'academic_type' => 'School',
            'teacher_id' => null,
            'status' => 'Active'
        ]);
        if (!$sid) {
            $stmtS = $db->prepare("SELECT id FROM subjects WHERE class_id = :cid AND subject_name = :sname LIMIT 1");
            $stmtS->execute(['cid' => $classId, 'sname' => $sname]);
            $sid = (int)$stmtS->fetchColumn();
        }
        $subjectIds[] = $sid;
    }

    // 2. Populate 1,000 Mock Active Students
    echo "[2/5] Seeding 1,000 Active Students into Class $classId...\n";
    $db->beginTransaction();
    $stmtStud = $db->prepare("
        INSERT INTO students (admission_no, first_name, last_name, gender, date_of_birth, enrollment_date, class_id, status, guardian_name, guardian_phone, address)
        VALUES (:adm, :fn, :ln, 'Male', '2010-01-01', '2025-04-01', :cid, 'Active', 'Guardian Name', '03001234567', 'Test Address')
        ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), class_id=VALUES(class_id)
    ");

    $studentIds = [];
    for ($i = 1; $i <= 1000; $i++) {
        $admNo = "BENCH-ADM-" . str_pad($i, 5, '0', STR_PAD_LEFT);
        $fn = "BenchStudent_" . $i;
        $ln = "Test";
        $stmtStud->execute([
            'adm' => $admNo,
            'fn'  => $fn,
            'ln'  => $ln,
            'cid' => $classId
        ]);
        
        $stmtGetId = $db->prepare("SELECT id FROM students WHERE admission_no = :adm LIMIT 1");
        $stmtGetId->execute(['adm' => $admNo]);
        $studentIds[] = (int)$stmtGetId->fetchColumn();
    }
    $db->commit();
    echo " -> 1,000 active students ready in DB.\n";

    // 3. Populate 6,000 Exam Marks
    echo "[3/5] Seeding 6,000 Student Mark Entries (6 subjects x 1,000 students)...\n";
    $db->beginTransaction();
    $stmtMark = $db->prepare("
        INSERT INTO student_marks (exam_type_id, student_id, subject_id, marks_obtained, status)
        VALUES (:etid, :sid, :subid, :obt, 'Present')
        ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained), status='Present'
    ");

    $markCount = 0;
    foreach ($studentIds as $idx => $sid) {
        $baseMarks = 35 + (($idx * 7) % 65); // ranges from 35 to 99
        foreach ($subjectIds as $subid) {
            $obt = min(100, max(0, $baseMarks + rand(-2, 2)));
            $stmtMark->execute([
                'etid'  => $examTypeId,
                'sid'   => $sid,
                'subid' => $subid,
                'obt'   => $obt
            ]);
            $markCount++;
        }
    }
    $db->commit();
    echo " -> $markCount mark records inserted.\n";

    // 4. Benchmark Result Processing for 1,000 Students
    echo "\n[4/5] BENCHMARKING: Generating Class Results & Rank Positions for 1,000 Students...\n";
    $genStart = microtime(true);
    
    $res = ExamResult::generateClassResults($examTypeId, $classId);
    
    $genEnd = microtime(true);
    $genTimeMs = round(($genEnd - $genStart) * 1000, 2);
    
    echo " -> Status: " . ($res['success'] ? 'SUCCESS' : 'FAILED: ' . ($res['message'] ?? '')) . "\n";
    echo " -> Class Results Generation Time: {$genTimeMs} ms\n";

    // 5. Benchmark Query & Rank Retrieval
    echo "\n[5/6] BENCHMARKING: Fetching Class Results & Positions for 1,000 Students...\n";
    $fetchStart = microtime(true);
    
    $results = ExamResult::getClassResults($examTypeId, $classId);
    
    $fetchEnd = microtime(true);
    $fetchTimeMs = round(($fetchEnd - $fetchStart) * 1000, 2);
    
    echo " -> Retrieved " . count($results) . " ranked student records.\n";
    echo " -> Fetch Time: {$fetchTimeMs} ms\n";

    // 6. Benchmark Batch Promotion of 1,000 Students
    echo "\n[6/6] BENCHMARKING: Batch Promoting 1,000 Students to Next Class...\n";
    $promoStart = microtime(true);
    
    $db->exec("INSERT INTO classes (class_name, section) VALUES ('Class 11', 'A') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $nextClassId = (int)$db->lastInsertId();
    if ($nextClassId <= 0) {
        $stmtNC = $db->query("SELECT id FROM classes WHERE class_name='Class 11' AND section='A' LIMIT 1");
        $nextClassId = (int)$stmtNC->fetchColumn();
    }

    $promoRes = Promotion::promoteStudents($studentIds, $classId, $nextClassId, '2026-2027');

    $promoEnd = microtime(true);
    $promoTimeMs = round(($promoEnd - $promoStart) * 1000, 2);
    
    $peakMemMb = round(memory_get_peak_usage() / 1024 / 1024, 2);

    echo " -> Status: " . ($promoRes['success'] ? 'SUCCESS' : 'FAILED: ' . ($promoRes['message'] ?? '')) . "\n";
    echo " -> Batch Promotion Time (1,000 Students): {$promoTimeMs} ms\n";
    echo " -> Peak Memory Usage: {$peakMemMb} MB\n\n";

    $totalTimeMs = round((microtime(true) - $startTime) * 1000, 2);
    echo "=========================================================\n";
    echo " ALL 6 BENCHMARKS COMPLETED IN {$totalTimeMs} ms\n";
    echo "=========================================================\n";

} catch (Exception $ex) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo "BENCHMARK ERROR: " . $ex->getMessage() . "\n";
    echo $ex->getTraceAsString() . "\n";
}
