<?php
/**
 * Indus Grammar School ERP - ExamResult Model
 * Version 4.0.0
 */

class ExamResult {

    public static function getStudentResult(int $examTypeId, int $studentId): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM exam_results WHERE exam_type_id = :etid AND student_id = :sid");
            $stmt->execute(['etid' => $examTypeId, 'sid' => $studentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ExamResult::getStudentResult error: " . $e->getMessage());
            return false;
        }
    }

    public static function getClassResults(int $examTypeId, int $classId): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT er.*, st.first_name, st.last_name, st.admission_no, st.roll_no, st.cnic_bform, st.guardian_cnic
                FROM exam_results er
                JOIN students st ON er.student_id = st.id
                WHERE er.exam_type_id = :etid AND er.class_id = :cid
                ORDER BY er.position ASC, st.first_name ASC
            ");
            $stmt->execute(['etid' => $examTypeId, 'cid' => $classId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ExamResult::getClassResults error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Compute and save results for all active students in a class (High-performance batch processing).
     */
    public static function generateClassResults(int $examTypeId, int $classId): array {
        try {
            $db = Database::getConnection();
            
            // 1. Fetch Exam details
            $exam = ExamType::findById($examTypeId);
            if (!$exam) return ['success' => false, 'message' => 'Exam type not found.'];

            // 2. Fetch all active students in class
            $stmtStuds = $db->prepare("SELECT id, first_name, last_name, admission_no FROM students WHERE class_id = :cid AND status = 'Active'");
            $stmtStuds->execute(['cid' => $classId]);
            $students = $stmtStuds->fetchAll(PDO::FETCH_ASSOC);

            if (empty($students)) return ['success' => false, 'message' => 'No active students found in this class.'];

            // 3. Fetch class subjects
            $stmtSubs = $db->prepare("SELECT id, total_marks, passing_marks FROM subjects WHERE class_id = :cid AND status = 'Active'");
            $stmtSubs->execute(['cid' => $classId]);
            $subjects = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

            if (empty($subjects)) return ['success' => false, 'message' => 'No active subjects assigned to this class.'];

            // 4. Batch fetch ALL student marks for this class & exam type in ONE single query
            $stmtAllMarks = $db->prepare("
                SELECT sm.student_id, sm.subject_id, sm.marks_obtained, sm.status 
                FROM student_marks sm
                JOIN students st ON sm.student_id = st.id
                WHERE sm.exam_type_id = :etid AND st.class_id = :cid AND st.status = 'Active'
            ");
            $stmtAllMarks->execute(['etid' => $examTypeId, 'cid' => $classId]);
            $allMarks = $stmtAllMarks->fetchAll(PDO::FETCH_ASSOC);

            $studentMarksMap = [];
            foreach ($allMarks as $m) {
                $studentMarksMap[(int)$m['student_id']][(int)$m['subject_id']] = $m;
            }

            // 5. Load Grade Setup scale index once in memory
            $gradeScales = GradeSetup::all();

            $resultsToRank = [];

            foreach ($students as $student) {
                $studentId = (int)$student['id'];
                $markMap = $studentMarksMap[$studentId] ?? [];

                $obtTotal = 0.00;
                $maxTotal = 0.00;
                $failedAny = false;
                $presentCount = 0;

                foreach ($subjects as $sub) {
                    $subId = (int)$sub['id'];
                    $maxTotal += (float)$sub['total_marks'];

                    if (isset($markMap[$subId])) {
                        $m = $markMap[$subId];
                        if ($m['status'] === 'Present' && $m['marks_obtained'] !== null) {
                            $obtTotal += (float)$m['marks_obtained'];
                            $presentCount++;
                            if ((float)$m['marks_obtained'] < (float)$sub['passing_marks']) {
                                $failedAny = true;
                            }
                        } else if ($m['status'] === 'Absent') {
                            $failedAny = true;
                        }
                    } else {
                        $failedAny = true; 
                    }
                }

                $percentage = $maxTotal > 0 ? ($obtTotal / $maxTotal) * 100 : 0.00;
                
                // Match grade using fast in-memory scanner
                $gradeInfo = GradeSetup::matchGrade($percentage, $gradeScales);
                $grade = $gradeInfo['grade'];
                
                $passStatus = 'Pass';
                if ($percentage < (float)$exam['passing_percentage'] || $failedAny) {
                    $passStatus = 'Fail';
                }

                $resultsToRank[] = [
                    'student_id' => $studentId,
                    'total_marks' => $maxTotal,
                    'obtained_marks' => $obtTotal,
                    'percentage' => $percentage,
                    'grade' => $grade,
                    'status' => $passStatus
                ];
            }

            // 6. Sort students by percentage & obtained marks descending
            usort($resultsToRank, function($a, $b) {
                if ($b['percentage'] == $a['percentage']) {
                    return $b['obtained_marks'] <=> $a['obtained_marks'];
                }
                return $b['percentage'] <=> $a['percentage'];
            });

            // Assign numerical position rankings
            foreach ($resultsToRank as $idx => &$resItem) {
                $resItem['position'] = $idx + 1;
            }
            unset($resItem);

            // 7. Save results and position rankings in database transaction using bulk chunks
            $db->beginTransaction();

            // Bulk save exam_results (chunks of 200)
            $chunks = array_chunk($resultsToRank, 200);
            
            foreach ($chunks as $chunk) {
                $placeholdersRes = [];
                $paramsRes = [];
                
                $placeholdersPos = [];
                $paramsPos = [];

                foreach ($chunk as $res) {
                    // exam_results values
                    $placeholdersRes[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    array_push($paramsRes, 
                        $examTypeId, $res['student_id'], $classId, 
                        $res['total_marks'], $res['obtained_marks'], $res['percentage'], 
                        $res['grade'], $res['position'], $res['status']
                    );

                    // positions values
                    $placeholdersPos[] = "(?, ?, ?, ?, ?, ?, ?)";
                    array_push($paramsPos,
                        $examTypeId, $classId, $res['student_id'],
                        $res['percentage'], $res['grade'], $res['position'], $res['status']
                    );
                }

                // Execute Bulk Insert for exam_results
                $sqlRes = "INSERT INTO exam_results (exam_type_id, student_id, class_id, total_marks, obtained_marks, percentage, grade, position, status)
                           VALUES " . implode(', ', $placeholdersRes) . "
                           ON DUPLICATE KEY UPDATE total_marks=VALUES(total_marks), obtained_marks=VALUES(obtained_marks), 
                           percentage=VALUES(percentage), grade=VALUES(grade), position=VALUES(position), status=VALUES(status)";
                $stmtBulkRes = $db->prepare($sqlRes);
                $stmtBulkRes->execute($paramsRes);

                // Execute Bulk Insert for positions
                $sqlPos = "INSERT INTO positions (exam_type_id, class_id, student_id, percentage, grade, position_no, status)
                           VALUES " . implode(', ', $placeholdersPos) . "
                           ON DUPLICATE KEY UPDATE percentage=VALUES(percentage), grade=VALUES(grade), 
                           position_no=VALUES(position_no), status=VALUES(status)";
                $stmtBulkPos = $db->prepare($sqlPos);
                $stmtBulkPos->execute($paramsPos);
            }

            $db->commit();
            auditLog('Results Generated', "Successfully generated results for Exam ID $examTypeId, Class ID $classId.");
            return ['success' => true, 'message' => 'Results compiled and rank positions generated.'];

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            error_log("ExamResult::generateClassResults error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Result processing failed: ' . $e->getMessage()];
        }
    }
}
